<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Support\Installation;

it('requires a signed-in owner', function (Role $role) {
    $this->actingAs(userWithRole($role))
        ->get(route('system.upgrade', ['key' => 'test-install-key']))
        ->assertForbidden();
})->with([Role::Manager, Role::Caretaker, Role::Tenant]);

it('redirects guests to login', function () {
    $this->get(route('system.upgrade', ['key' => 'test-install-key']))->assertRedirect(route('login'));
});

it('requires the install key even for the owner', function () {
    $owner = userWithRole(Role::Owner);

    $this->actingAs($owner)->get(route('system.upgrade'))->assertForbidden();
    $this->actingAs($owner)->get(route('system.upgrade', ['key' => 'wrong']))->assertForbidden();
});

it('shows versions and runs pending migrations for the owner with the key', function () {
    $owner = userWithRole(Role::Owner);

    $this->actingAs($owner)->get(route('system.upgrade', ['key' => 'test-install-key']))
        ->assertOk()
        ->assertSee(config('rentora.version'))
        ->assertSee(__('upgrade.none_pending'));

    $this->actingAs($owner)->post(route('system.upgrade.run'), ['key' => 'test-install-key'])
        ->assertRedirect(route('system.upgrade', ['key' => 'test-install-key']));

    expect(Installation::installedVersion())->toBe(config('rentora.version'));
});
