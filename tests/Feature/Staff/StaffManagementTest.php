<?php

declare(strict_types=1);

use App\Actions\Staff\InviteStaff;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\User;
use App\Notifications\StaffInvitation;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

it('lets the owner invite a staff member', function () {
    Notification::fake();
    $owner = userWithRole(Role::Owner);

    $this->actingAs($owner)->post(route('app.staff.store'), [
        'name' => 'Musa Bello',
        'email' => 'musa@example.com',
        'role' => 'caretaker',
    ])->assertRedirect(route('app.staff.index'));

    $member = User::query()->where('email', 'musa@example.com')->firstOrFail();
    expect($member->role)->toBe(Role::Caretaker)->and($member->organization_id)->toBe($owner->organization_id);
    Notification::assertSentTo($member, StaffInvitation::class);
});

it('does not allow inviting a tenant through staff management', function () {
    $this->actingAs(userWithRole(Role::Owner))->post(route('app.staff.store'), [
        'name' => 'X', 'email' => 'x@example.com', 'role' => 'tenant',
    ])->assertSessionHasErrors('role');
});

it('lists staff with search', function () {
    $owner = userWithRole(Role::Owner);
    userWithRole(Role::Manager, $owner->organization, ['name' => 'Chika Eze']);
    userWithRole(Role::Manager, $owner->organization, ['name' => 'Tunde Ade']);

    $this->actingAs($owner)->get(route('app.staff.index', ['q' => 'Chika']))
        ->assertOk()
        ->assertSee('Chika Eze')
        ->assertDontSee('Tunde Ade');
});

it('changes a role and logs it', function () {
    $owner = userWithRole(Role::Owner);
    $member = userWithRole(Role::Caretaker, $owner->organization);

    $this->actingAs($owner)->put(route('app.staff.update', $member), ['role' => 'manager', 'is_active' => '1'])
        ->assertRedirect(route('app.staff.index'));

    expect($member->fresh()->role)->toBe(Role::Manager)
        ->and(ActivityLog::query()->where('action', 'staff.role_changed')->where('subject_id', $member->id)->exists())->toBeTrue();
});

it('deactivates a staff member', function () {
    $owner = userWithRole(Role::Owner);
    $member = userWithRole(Role::Manager, $owner->organization);

    $this->actingAs($owner)->put(route('app.staff.update', $member), ['role' => 'manager', 'is_active' => '0']);

    expect($member->fresh()->is_active)->toBeFalse()
        ->and(ActivityLog::query()->where('action', 'staff.deactivated')->exists())->toBeTrue();
});

it('stops the owner from demoting or deactivating themselves', function () {
    $owner = userWithRole(Role::Owner);

    $this->actingAs($owner)->put(route('app.staff.update', $owner), ['role' => 'manager', 'is_active' => '1'])
        ->assertSessionHasErrors('role');

    expect($owner->fresh()->role)->toBe(Role::Owner);
});

it('keeps at least one active owner', function () {
    $owner = userWithRole(Role::Owner);
    $other = userWithRole(Role::Owner, $owner->organization);

    $this->actingAs($owner)->put(route('app.staff.update', $other), ['role' => 'manager', 'is_active' => '1'])
        ->assertSessionHasNoErrors();
    $this->actingAs($other->fresh())->get('/app');

    expect($other->fresh()->role)->toBe(Role::Manager);
});

it('returns 404 for a staff member of another organization', function () {
    $owner = userWithRole(Role::Owner);
    $stranger = userWithRole(Role::Manager);

    $this->actingAs($owner)->put(route('app.staff.update', $stranger), ['role' => 'caretaker', 'is_active' => '1'])
        ->assertNotFound();

    expect($stranger->fresh()->role)->toBe(Role::Manager);
});

it('accepts an invitation once and logs the new member in', function () {
    $member = userWithRole(Role::Manager);
    $link = InviteStaff::link($member);

    $this->get($link)->assertOk();
    $this->post($link, ['password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])
        ->assertRedirect(route('app.dashboard'));
    $this->assertAuthenticatedAs($member);

    auth()->logout();
    $this->get($link)->assertForbidden();
});

it('rejects an expired or tampered invitation link', function () {
    $member = userWithRole(Role::Manager);
    $link = InviteStaff::link($member);

    $this->get($link.'x')->assertForbidden();

    $this->travel(8)->days();
    $this->get($link)->assertForbidden();
});

it('rejects an invitation link signed for a different user', function () {
    $member = userWithRole(Role::Manager);
    $other = userWithRole(Role::Manager, $member->organization);
    $forged = URL::temporarySignedRoute('invite.staff', now()->addDay(), [
        'user' => $other->id, 'h' => InviteStaff::fingerprint($member),
    ]);

    $this->get($forged)->assertForbidden();
});
