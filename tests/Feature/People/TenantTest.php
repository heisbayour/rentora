<?php

declare(strict_types=1);

use App\Actions\Tenants\InviteTenant;
use App\Enums\DocumentVisibility;
use App\Enums\Role;
use App\Enums\TenantStatus;
use App\Models\Document;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\TenantInvitation;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

it('creates, lists and updates a tenant', function () {
    $manager = userWithRole(Role::Manager);

    $this->actingAs($manager)->post(route('app.tenants.store'), [
        'full_name' => 'Adaeze Okafor',
        'email' => 'ADA@example.com',
        'phone' => '08031234567',
        'notification_prefs' => ['email' => '1', 'sms' => '0'],
    ])->assertRedirect();

    $tenant = Tenant::query()->firstOrFail();
    expect($tenant->email)->toBe('ada@example.com')
        ->and($tenant->status)->toBe(TenantStatus::PendingInvite)
        ->and($tenant->wants('email'))->toBeTrue()
        ->and($tenant->wants('sms'))->toBeFalse();

    $this->actingAs($manager)->get(route('app.tenants.index', ['q' => 'Adaeze']))->assertOk()->assertSee('Adaeze Okafor');

    $this->actingAs($manager)->put(route('app.tenants.update', $tenant), [
        'full_name' => 'Adaeze Okafor-Bello', 'phone' => '08031234567',
    ])->assertRedirect(route('app.tenants.show', $tenant));
    expect($tenant->fresh()->full_name)->toBe('Adaeze Okafor-Bello');
});

it('shows every profile tab to managers and only contacts to caretakers', function () {
    $manager = userWithRole(Role::Manager);
    $tenant = Tenant::factory()->create(['organization_id' => $manager->organization_id, 'phone' => '08039998888']);

    foreach (['overview', 'leases', 'documents', 'activity'] as $tab) {
        $this->actingAs($manager)->get(route('app.tenants.show', [$tenant, 'tab' => $tab]))->assertOk();
    }

    $caretaker = userWithRole(Role::Caretaker, $manager->organization);
    $this->actingAs($caretaker)->get(route('app.tenants.show', [$tenant, 'tab' => 'documents']))
        ->assertOk()->assertSee('08039998888')->assertDontSee(__('tenants.tabs.documents'));
    $this->actingAs($caretaker)->get(route('app.tenants.edit', $tenant))->assertForbidden();
});

it('returns 404 for a tenant in another organization', function () {
    $this->actingAs(userWithRole(Role::Manager))->get(route('app.tenants.show', Tenant::factory()->create()))->assertNotFound();
});

it('invites a tenant with a 7-day link that activates a portal account', function () {
    Notification::fake();
    $manager = userWithRole(Role::Manager);
    $tenant = Tenant::factory()->create(['organization_id' => $manager->organization_id, 'email' => 'ada@example.com']);

    $this->actingAs($manager)->post(route('app.tenants.invite', $tenant))->assertRedirect();
    Notification::assertSentTo($tenant, TenantInvitation::class);
    auth()->logout();

    $link = InviteTenant::link($tenant->fresh());
    $this->get($link)->assertOk()->assertSee('ada@example.com');
    $this->post($link, ['password' => 'secret123', 'password_confirmation' => 'secret123'])->assertRedirect(route('portal.home'));

    $user = User::query()->where('email', 'ada@example.com')->firstOrFail();
    expect($user->role)->toBe(Role::Tenant)
        ->and($tenant->fresh()->user_id)->toBe($user->id)
        ->and($tenant->fresh()->status)->toBe(TenantStatus::Active);
    $this->assertAuthenticatedAs($user);
});

it('expires the invite link after 7 days', function () {
    $tenant = Tenant::factory()->create(['organization_id' => organization()->id]);
    $tenant->forceFill(['invite_sent_at' => now()])->save();
    $link = InviteTenant::link($tenant);

    $this->travel(7)->days();
    $this->travel(1)->minutes();

    $this->get($link)->assertForbidden();
});

it('invalidates an older link when the invite is resent, and after acceptance', function () {
    $tenant = Tenant::factory()->create(['organization_id' => organization()->id]);
    $tenant->forceFill(['invite_sent_at' => now()->subDay()])->save();
    $old = InviteTenant::link($tenant);

    $tenant->forceFill(['invite_sent_at' => now()])->save();
    $new = InviteTenant::link($tenant);

    $this->get($old)->assertForbidden();
    $this->post($new, ['password' => 'secret123', 'password_confirmation' => 'secret123'])->assertRedirect();
    auth()->logout();
    $this->get($new)->assertForbidden();
});

it('lets a tenant invited by SMS choose their login email', function () {
    Notification::fake();
    $tenant = Tenant::factory()->withoutEmail()->create(['organization_id' => organization()->id]);
    $tenant->forceFill(['invite_sent_at' => now()])->save();
    $link = InviteTenant::link($tenant);

    $this->post($link, ['password' => 'secret123', 'password_confirmation' => 'secret123'])->assertSessionHasErrors('email');
    $this->post($link, ['email' => 'new@example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123'])->assertRedirect();

    expect($tenant->fresh()->email)->toBe('new@example.com');
});

it('lets a tenant download their own tenant-visible lease documents only', function () {
    Storage::fake('local');
    $organization = organization();
    $unit = Unit::factory()->create(['property_id' => Property::factory()->create(['organization_id' => $organization->id])->id]);
    $lease = Lease::factory()->create(['unit_id' => $unit->id]);
    $tenantUser = userWithRole(Role::Tenant, $organization);
    $lease->tenant->forceFill(['user_id' => $tenantUser->id])->save();

    $shared = $lease->documents()->create(['name' => 'Agreement', 'path' => 'documents/lease/a.pdf', 'mime' => 'application/pdf', 'size' => 10, 'visibility' => DocumentVisibility::Tenant]);
    $internal = $lease->documents()->create(['name' => 'Notes', 'path' => 'documents/lease/b.pdf', 'mime' => 'application/pdf', 'size' => 10, 'visibility' => DocumentVisibility::Internal]);
    Storage::disk('local')->put($shared->path, 'pdf');
    Storage::disk('local')->put($internal->path, 'pdf');

    $otherLease = Lease::factory()->create(['unit_id' => Unit::factory()->create(['property_id' => $unit->property_id])->id]);
    $foreign = $otherLease->documents()->create(['name' => 'Other', 'path' => 'documents/lease/c.pdf', 'mime' => 'application/pdf', 'size' => 10, 'visibility' => DocumentVisibility::Tenant]);

    $this->actingAs($tenantUser)->get(route('files.documents', $shared))->assertOk();
    $this->actingAs($tenantUser)->get(route('files.documents', $internal))->assertForbidden();
    $this->actingAs($tenantUser)->get(route('files.documents', $foreign))->assertForbidden();
    expect(Document::query()->count())->toBe(3);
});
