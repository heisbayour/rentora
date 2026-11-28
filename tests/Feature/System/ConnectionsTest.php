<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\SystemState;
use App\Models\User;
use App\Services\Scheduling\TaskRegistry;
use App\Services\SettingsService;
use App\Services\System\ConnectionChecks;
use App\Services\System\SmtpProbe;
use App\Support\CurrentOrganization;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/*
 * System health "Connections" (DECISIONS 108): Email, SMS and Paystack are
 * each not set up, working or failing, checked daily and on "Test now",
 * without sending any real message.
 */

function connectionSettings(User $owner, array $values, array $encrypted = []): void
{
    app(CurrentOrganization::class)->runAs($owner->organization, fn () => app(SettingsService::class)->setMany($values, $encrypted));
}

function configureAllConnections(User $owner): void
{
    connectionSettings($owner, [
        'mail.host' => 'smtp.example.test', 'mail.port' => '587', 'mail.username' => 'user', 'mail.password' => 'secret',
        'sms.driver' => 'termii', 'sms.termii_api_key' => 'TLkey1234', 'sms.termii_sender_id' => 'Rentora',
        'paystack.public_key' => 'pk_test_x', 'paystack.secret_key' => 'sk_test_x',
    ], ['mail.password', 'sms.termii_api_key', 'paystack.secret_key']);
}

it('shows every connection as not set up on a fresh install', function () {
    $owner = userWithRole(Role::Owner);

    $response = $this->actingAs($owner)->get(route('app.health'))->assertOk()
        ->assertSee(__('health.connections.title'))
        ->assertSee(__('health.connections.kinds.email'))
        ->assertSee(__('health.connections.kinds.sms'))
        ->assertSee(__('health.connections.kinds.paystack'))
        ->assertSee(__('health.connections.test'))
        ->assertSee(__('health.outbox_help'));
    expect(substr_count($response->getContent(), __('health.connections.status.not_set_up')))->toBe(3);
});

it('tests each connection without sending a message and shows working with the time', function () {
    Mail::fake();
    $owner = userWithRole(Role::Owner);
    configureAllConnections($owner);
    $this->mock(SmtpProbe::class, fn ($mock) => $mock->shouldReceive('check')->once());
    Http::fake([
        '*/api/get-balance*' => Http::response(['user' => 'Rentora', 'balance' => 1520.5, 'currency' => 'NGN']),
        '*/bank*' => Http::response(['status' => true, 'data' => []]),
    ]);

    foreach (ConnectionChecks::KINDS as $kind) {
        $this->actingAs($owner)->from(route('app.health'))->post(route('app.health.connections.test', $kind))
            ->assertRedirect(route('app.health'))
            ->assertSessionHas('toast.type', 'success');
    }

    Mail::assertNothingSent();
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/api/sms/send'));
    Http::assertSent(fn (Request $request) => $request->method() === 'GET' && str_contains($request->url(), '/bank'));

    $response = $this->actingAs($owner)->get(route('app.health'))->assertOk()->assertSee('1520.5 NGN');
    expect(substr_count($response->getContent(), __('health.connections.status.working')))->toBe(3)
        ->and($response->getContent())->toContain('Last checked');
});

it('shows a failing connection with its error, and warns owners and developers on the dashboard', function () {
    $owner = userWithRole(Role::Owner);
    $developer = userWithRole(Role::Developer, $owner->organization);
    $manager = userWithRole(Role::Manager, $owner->organization);
    configureAllConnections($owner);
    $this->mock(SmtpProbe::class, fn ($mock) => $mock->shouldReceive('check')->andThrow(new RuntimeException('535 Authentication failed')));
    Http::fake([
        '*/api/get-balance*' => Http::response(['message' => 'Invalid API key'], 401),
        '*/bank*' => Http::response(['status' => false, 'message' => 'Invalid key'], 401),
    ]);

    $this->actingAs($owner)->post(route('app.health.connections.test', 'email'))->assertSessionHas('toast.type', 'danger');
    $this->actingAs($owner)->post(route('app.health.connections.test', 'sms'))->assertSessionHas('toast.type', 'danger');
    $this->actingAs($owner)->post(route('app.health.connections.test', 'paystack'))->assertSessionHas('toast.type', 'danger');

    $this->actingAs($owner)->get(route('app.health'))
        ->assertSee(__('health.connections.status.failing'))
        ->assertSee('535 Authentication failed')
        ->assertSee('Termii 401: Invalid API key')
        ->assertSee('Paystack 401: Invalid key');

    $warning = __('health.connections.dashboard_warning', ['names' => 'Email (SMTP), SMS (Termii), Paystack']);
    $this->actingAs($owner)->get(route('app.dashboard'))->assertOk()->assertSee($warning);
    $this->actingAs($developer)->get(route('app.dashboard'))->assertOk()->assertSee($warning);
    $this->actingAs($manager)->get(route('app.dashboard'))->assertOk()->assertDontSee('data-connection-warning', false);
});

it('stops warning once a failing connection is no longer configured', function () {
    $owner = userWithRole(Role::Owner);
    connectionSettings($owner, ['paystack.public_key' => 'pk_test_x', 'paystack.secret_key' => 'sk_test_x'], ['paystack.secret_key']);
    Http::fake(['*/bank*' => Http::response(['message' => 'Invalid key'], 401)]);
    $this->actingAs($owner)->post(route('app.health.connections.test', 'paystack'));
    expect(app(CurrentOrganization::class)->runAs($owner->organization, fn () => app(ConnectionChecks::class)->failing()))->toBe(['paystack']);

    app(CurrentOrganization::class)->runAs($owner->organization, function () {
        app(SettingsService::class)->forget('paystack.secret_key');
        app(SettingsService::class)->flush();
    });

    $this->actingAs($owner)->get(route('app.dashboard'))->assertDontSee('data-connection-warning', false);
});

it('runs the checks once a day from the scheduler and stores the results', function () {
    $owner = userWithRole(Role::Owner);
    connectionSettings($owner, ['paystack.public_key' => 'pk_test_x', 'paystack.secret_key' => 'sk_test_x'], ['paystack.secret_key']);
    Http::fake(['*/bank*' => Http::response(['status' => true, 'data' => []])]);

    $task = app(TaskRegistry::class)->tasks()['check_connections'];
    expect($task['every'])->toBe('daily')
        ->and(($task['run'])(microtime(true) + 10))->toBe(1);

    $stored = json_decode((string) SystemState::getValue('connection.'.$owner->organization_id.'.paystack'), true);
    expect($stored['status'])->toBe('working')
        ->and(json_decode((string) SystemState::getValue('connection.'.$owner->organization_id.'.email'), true)['status'])->toBe('not_set_up');
});

it('reports a real SMTP connection failure from the probe', function () {
    $owner = userWithRole(Role::Owner);
    connectionSettings($owner, ['mail.host' => '127.0.0.1', 'mail.port' => '1', 'mail.encryption' => 'tls']);

    $result = app(CurrentOrganization::class)->runAs($owner->organization, fn () => app(ConnectionChecks::class)->run('email'));

    expect($result['status'])->toBe('failing')
        ->and($result['error'])->toContain('127.0.0.1');
});

it('only lets owners and developers test connections', function () {
    $manager = userWithRole(Role::Manager);

    $this->actingAs($manager)->post(route('app.health.connections.test', 'email'))->assertForbidden();
    $this->actingAs(userWithRole(Role::Owner))->post(route('app.health.connections.test', 'fax'))->assertNotFound();
});
