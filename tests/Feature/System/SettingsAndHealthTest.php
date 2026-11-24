<?php

declare(strict_types=1);

use App\Enums\MessageStatus;
use App\Enums\Role;
use App\Models\OutboundMessage;
use App\Models\Property;
use App\Models\Setting;
use App\Models\Unit;
use App\Services\LeaseWizard;
use App\Services\SettingsService;
use App\Support\AppVersion;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Http;

it('renders every settings tab and health for the owner only', function () {
    $owner = userWithRole(Role::Owner);
    $manager = userWithRole(Role::Manager, $owner->organization);

    foreach (['organization', 'website', 'billing', 'payments', 'email', 'sms'] as $tab) {
        $this->actingAs($owner)->get(route('app.settings.edit', ['tab' => $tab]))->assertOk();
    }
    $this->actingAs($owner)->get(route('app.settings.edit', ['tab' => 'staff']))->assertRedirect(route('app.staff.index'));
    $this->actingAs($owner)->get(route('app.health'))->assertOk()->assertSee('drain_outbox');

    $this->actingAs($manager)->get(route('app.settings.edit'))->assertForbidden();
    $this->actingAs($manager)->put(route('app.settings.update', 'payments'), ['paystack__mode' => 'live'])->assertForbidden();
    $this->actingAs($manager)->get(route('app.health'))->assertForbidden();
});

it('stores secrets encrypted, shows only the last 4 characters and keeps them when left empty', function () {
    $owner = userWithRole(Role::Owner);

    $this->actingAs($owner)->put(route('app.settings.update', 'payments'), [
        'paystack__mode' => 'test', 'paystack__public_key' => 'pk_test_public123', 'paystack__secret_key' => 'sk_test_supersecret9876',
    ])->assertRedirect();

    $row = Setting::query()->where('key', 'paystack.secret_key')->sole();
    expect($row->is_encrypted)->toBeTrue()->and($row->value)->not->toContain('supersecret');

    $this->actingAs($owner)->get(route('app.settings.edit', ['tab' => 'payments']))
        ->assertSee('9876')
        ->assertDontSee('sk_test_supersecret9876');

    $this->actingAs($owner)->put(route('app.settings.update', 'payments'), [
        'paystack__mode' => 'live', 'paystack__public_key' => 'pk_test_public123', 'paystack__secret_key' => '',
    ])->assertRedirect();

    $secret = app(CurrentOrganization::class)->runAs($owner->organization, fn () => app(SettingsService::class)->get('paystack.secret_key'));
    expect($secret)->toBe('sk_test_supersecret9876');
});

it('saves billing defaults and uses them for new leases', function () {
    $owner = userWithRole(Role::Owner);

    $this->actingAs($owner)->put(route('app.settings.update', 'billing'), [
        'billing__default_grace_days' => 7, 'billing__late_fee_type' => 'percent', 'billing__late_fee_value' => '5',
        'billing__allow_overpayment' => '1', 'billing__bank_name' => 'GTBank', 'billing__bank_account_number' => '0123456789', 'billing__bank_account_name' => 'Demo',
    ])->assertRedirect()->assertSessionHasNoErrors();

    app(CurrentOrganization::class)->runAs($owner->organization, function () {
        $settings = app(SettingsService::class);
        expect($settings->int('billing.late_fee_value'))->toBe(500)
            ->and($settings->int('billing.default_grace_days'))->toBe(7)
            ->and($settings->bool('billing.allow_overpayment'))->toBeTrue();

        $terms = app(LeaseWizard::class)->defaultTerms(Unit::factory()->create(['property_id' => Property::factory()->create(['organization_id' => app(CurrentOrganization::class)->id()])->id]));
        expect($terms['grace_days'])->toBe(7)->and($terms['late_fee_type'])->toBe('percent')->and($terms['late_fee_value'])->toBe(500);
    });
});

it('turns the public website off from settings', function () {
    $owner = userWithRole(Role::Owner);

    $this->actingAs($owner)->put(route('app.settings.update', 'website'), ['enabled' => '0', 'website_headline' => 'Homes'])->assertRedirect();
    auth()->logout();

    $this->get('/')->assertRedirect(route('login'));
    expect($owner->organization->fresh()->website_headline)->toBe('Homes');
});

it('runs the Paystack, email and SMS test buttons', function () {
    $owner = userWithRole(Role::Owner);
    app(CurrentOrganization::class)->runAs($owner->organization, fn () => app(SettingsService::class)
        ->setMany(['paystack.public_key' => 'pk_test_x', 'paystack.secret_key' => 'sk_test_x'], ['paystack.secret_key']));
    Http::fake(['*/bank*' => Http::response(['status' => true, 'data' => []])]);

    $this->actingAs($owner)->post(route('app.settings.test', 'paystack'))->assertSessionHas('toast.type', 'success');
    $this->actingAs($owner)->post(route('app.settings.test', 'email'), ['to' => 'owner@example.test'])->assertSessionHas('toast.type', 'success');
    $this->actingAs($owner)->post(route('app.settings.test', 'sms'), ['to' => '08031234567'])->assertSessionHas('toast.type', 'success');
    $this->actingAs($owner)->post(route('app.settings.test', 'sms'), ['to' => '12'])->assertSessionHas('toast.type', 'danger');
});

it('retries failed messages and drains the outbox from system health', function () {
    $owner = userWithRole(Role::Owner);
    $failed = OutboundMessage::factory()->create(['organization_id' => $owner->organization_id, 'status' => 'failed', 'attempts' => 4, 'last_error' => 'SMTP down']);

    $this->actingAs($owner)->get(route('app.health'))->assertSee('SMTP down');
    $this->actingAs($owner)->post(route('app.health.retry'))->assertRedirect();
    expect($failed->fresh()->status)->toBe(MessageStatus::Queued)->and($failed->fresh()->attempts)->toBe(0);

    $this->actingAs($owner)->post(route('app.health.drain'))->assertRedirect();
    expect($failed->fresh()->status)->toBe(MessageStatus::Sent);
});

it('shows the app version from the VERSION file in every layout footer', function () {
    $owner = userWithRole(Role::Owner);
    $version = trim((string) file_get_contents(base_path('VERSION')));

    expect(config('rentora.version'))->toBe($version)
        ->and(AppVersion::label())->toStartWith(config('rentora.name').' v'.$version);

    $this->actingAs($owner)->get(route('app.dashboard'))->assertSee('v'.$version);
    auth()->logout();
    $this->get('/')->assertSee('v'.$version);
});
