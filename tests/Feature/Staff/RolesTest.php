<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Messaging\Notifier;
use Illuminate\Support\Facades\Artisan;

/*
 * v1.0.4 roles (DECISIONS 105 and 107): the developer (support) role, owners
 * changing roles and removing staff, and the last active owner rule.
 */

it('gives the developer every owner page plus the system pages', function () {
    $developer = userWithRole(Role::Developer);

    foreach (['app.staff.index', 'app.settings.edit', 'app.health', 'app.audit.index', 'app.payments.index'] as $route) {
        $this->actingAs($developer)->get(route($route))->assertOk();
    }
    $this->actingAs($developer)->get(route('system.upgrade', ['key' => 'test-install-key']))->assertOk();
    $this->actingAs($developer)->get(route('system.reset', ['key' => 'test-install-key']))->assertOk();
    $this->actingAs($developer)->get(route('app.dashboard'))->assertOk()->assertSee(route('app.health'), false);
});

it('shows the developer as "Developer (support)" in the staff list', function () {
    $owner = userWithRole(Role::Owner);
    userWithRole(Role::Developer, $owner->organization, ['name' => 'Support Dev']);

    $this->actingAs($owner)->get(route('app.staff.index'))->assertOk()
        ->assertSee('Support Dev')
        ->assertSee('Developer (support)');
});

it('lets a developer change anyone\'s role and remove any account, owners included', function () {
    $developer = userWithRole(Role::Developer);
    $owner = userWithRole(Role::Owner, $developer->organization);
    $second = userWithRole(Role::Owner, $developer->organization);
    $otherDeveloper = userWithRole(Role::Developer, $developer->organization);

    $this->actingAs($developer)->put(route('app.staff.update', $owner), ['role' => 'manager', 'is_active' => '1'])->assertSessionHasNoErrors();
    expect($owner->fresh()->role)->toBe(Role::Manager);

    $this->actingAs($developer)->put(route('app.staff.update', $owner), ['role' => 'developer', 'is_active' => '1'])->assertSessionHasNoErrors();
    expect($owner->fresh()->role)->toBe(Role::Developer);

    $this->actingAs($developer)->put(route('app.staff.update', $otherDeveloper), ['role' => 'caretaker', 'is_active' => '0'])->assertSessionHasNoErrors();
    expect($otherDeveloper->fresh())->role->toBe(Role::Caretaker)->is_active->toBeFalse();

    $this->actingAs($developer)->delete(route('app.staff.destroy', $owner))->assertSessionHasNoErrors();
    expect(User::query()->find($owner->id))->toBeNull()
        ->and($second->fresh()->role)->toBe(Role::Owner);
});

it('stops owners from demoting, deactivating, removing or promoting to developer', function () {
    $owner = userWithRole(Role::Owner);
    $developer = userWithRole(Role::Developer, $owner->organization);
    $manager = userWithRole(Role::Manager, $owner->organization);

    $this->actingAs($owner)->put(route('app.staff.update', $developer), ['role' => 'manager', 'is_active' => '1'])->assertSessionHasErrors('role');
    $this->actingAs($owner)->put(route('app.staff.update', $developer), ['role' => 'developer', 'is_active' => '0'])->assertSessionHasErrors('role');
    $this->actingAs($owner)->delete(route('app.staff.destroy', $developer))->assertSessionHasErrors('role');
    expect($developer->fresh())->role->toBe(Role::Developer)->is_active->toBeTrue();

    $this->actingAs($owner)->put(route('app.staff.update', $manager), ['role' => 'developer', 'is_active' => '1'])->assertSessionHasErrors('role');
    $this->actingAs($owner)->post(route('app.staff.store'), ['name' => 'X', 'email' => 'x@example.com', 'role' => 'developer'])->assertSessionHasErrors('role');
    expect($manager->fresh()->role)->toBe(Role::Manager)
        ->and(User::query()->where('email', 'x@example.com')->exists())->toBeFalse();

    // Owners do not get an edit button for the developer at all.
    $this->actingAs($owner)->get(route('app.staff.index'))->assertDontSee('edit-staff-'.$developer->id);
});

it('lets owners change roles between owner, manager and caretaker', function () {
    $owner = userWithRole(Role::Owner);
    $member = userWithRole(Role::Caretaker, $owner->organization);

    foreach (['manager', 'owner', 'caretaker'] as $role) {
        $this->actingAs($owner)->put(route('app.staff.update', $member), ['role' => $role, 'is_active' => '1'])->assertSessionHasNoErrors();
        expect($member->fresh()->role->value)->toBe($role);
    }
    expect(ActivityLog::query()->where('action', 'staff.role_changed')->where('subject_id', $member->id)->count())->toBe(3);
});

it('deletes a staff account with no history', function () {
    $owner = userWithRole(Role::Owner);
    $member = userWithRole(Role::Caretaker, $owner->organization, ['name' => 'Never Used']);

    $this->actingAs($owner)->delete(route('app.staff.destroy', $member))
        ->assertRedirect(route('app.staff.index'))
        ->assertSessionHas('toast', __('staff.removed_deleted', ['name' => 'Never Used']));

    expect(User::query()->find($member->id))->toBeNull()
        ->and(ActivityLog::query()->where('action', 'staff.removed')->where('subject_id', $member->id)->value('properties'))
        ->toMatchArray(['name' => 'Never Used', 'role' => 'caretaker', 'mode' => 'deleted']);
});

it('deactivates and anonymises a staff account with history, keeping the audit log', function () {
    $owner = userWithRole(Role::Owner);
    $member = userWithRole(Role::Manager, $owner->organization, ['name' => 'Chika Eze', 'email' => 'chika@example.com', 'phone' => '08031234567']);
    $entry = app(ActivityLogger::class)->log('payment.approved', null, [], $member);
    $payment = Payment::factory()->create(['organization_id' => $owner->organization_id, 'recorded_by' => $member->id]);

    $this->actingAs($owner)->delete(route('app.staff.destroy', $member))
        ->assertSessionHas('toast', __('staff.removed_anonymised', ['name' => 'Chika Eze']));

    $removed = User::query()->findOrFail($member->id);
    expect($removed)
        ->is_active->toBeFalse()
        ->removed_at->not->toBeNull()
        ->name->toBe(__('staff.removed_name'))
        ->phone->toBeNull()
        ->and($removed->email)->not->toBe('chika@example.com')
        ->and($entry->fresh()->user_id)->toBe($member->id)
        ->and($payment->fresh()->recorded_by)->toBe($member->id);

    // Gone from the staff list and unable to sign in.
    $this->actingAs($owner)->get(route('app.staff.index'))->assertDontSee('chika@example.com')->assertDontSee(__('staff.removed_name'));
    auth()->logout();
    $this->post(route('login'), ['email' => 'chika@example.com', 'password' => 'password'])->assertSessionHasErrors();
    $this->assertGuest();
});

it('never demotes, deactivates or removes the last active owner', function () {
    $owner = userWithRole(Role::Owner);
    $developer = userWithRole(Role::Developer, $owner->organization);

    $this->actingAs($developer)->put(route('app.staff.update', $owner), ['role' => 'manager', 'is_active' => '1'])->assertSessionHasErrors('role');
    $this->actingAs($developer)->put(route('app.staff.update', $owner), ['role' => 'owner', 'is_active' => '0'])->assertSessionHasErrors('role');
    $this->actingAs($developer)->delete(route('app.staff.destroy', $owner))->assertSessionHasErrors('member');

    expect($owner->fresh())->role->toBe(Role::Owner)->is_active->toBeTrue();
});

it('stops anyone removing or demoting themselves', function () {
    $owner = userWithRole(Role::Owner);
    userWithRole(Role::Owner, $owner->organization);
    $developer = userWithRole(Role::Developer, $owner->organization);

    $this->actingAs($owner)->delete(route('app.staff.destroy', $owner))->assertSessionHasErrors('member');
    $this->actingAs($developer)->delete(route('app.staff.destroy', $developer))->assertSessionHasErrors('member');
    $this->actingAs($developer)->put(route('app.staff.update', $developer), ['role' => 'owner', 'is_active' => '1'])->assertSessionHasErrors('role');

    expect($owner->fresh())->not->toBeNull()->and($developer->fresh()->role)->toBe(Role::Developer);
});

it('does not let managers or caretakers remove staff or reach another organization', function () {
    $owner = userWithRole(Role::Owner);
    $manager = userWithRole(Role::Manager, $owner->organization);
    $caretaker = userWithRole(Role::Caretaker, $owner->organization);
    $stranger = userWithRole(Role::Caretaker);

    $this->actingAs($manager)->delete(route('app.staff.destroy', $caretaker))->assertForbidden();
    $this->actingAs($owner)->delete(route('app.staff.destroy', $stranger))->assertNotFound();
    expect($caretaker->fresh())->not->toBeNull()->and($stranger->fresh())->not->toBeNull();
});

it('keeps developer accounts through the clean-slate reset', function () {
    $owner = userWithRole(Role::Owner);
    $developer = userWithRole(Role::Developer, $owner->organization);
    $manager = userWithRole(Role::Manager, $owner->organization);

    $this->actingAs($developer)->post(route('system.reset.run'), ['key' => 'test-install-key', 'confirm' => 'RESET', 'password' => 'password'])
        ->assertSessionHasNoErrors();

    expect($developer->fresh())->not->toBeNull()
        ->and($owner->fresh())->not->toBeNull()
        ->and(User::query()->find($manager->id))->toBeNull();
});

it('sends owner messages to the developer only while there is no active owner', function () {
    $organization = organization();
    $developer = userWithRole(Role::Developer, $organization);
    $manager = userWithRole(Role::Manager, $organization);
    $notifier = app(Notifier::class);

    expect($notifier->staff($organization->id)->pluck('id')->sort()->values()->all())->toBe(collect([$developer->id, $manager->id])->sort()->values()->all())
        ->and($notifier->staff($organization->id, [Role::Manager])->pluck('id')->all())->toBe([$manager->id]);

    $owner = userWithRole(Role::Owner, $organization);
    expect($notifier->staff($organization->id)->pluck('id')->sort()->values()->all())->toBe(collect([$owner->id, $manager->id])->sort()->values()->all());
});

it('makes an existing account the developer with rentora:make-developer', function () {
    $owner = userWithRole(Role::Owner, null, ['email' => 'support@example.com']);

    expect(Artisan::call('rentora:make-developer', ['email' => 'Support@Example.com']))->toBe(0)
        ->and($owner->fresh()->role)->toBe(Role::Developer)
        ->and(Artisan::call('rentora:make-developer', ['email' => 'nobody@example.com']))->toBe(1);
});
