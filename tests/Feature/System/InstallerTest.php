<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use App\Support\Installation;

beforeEach(fn () => Installation::forget());

afterEach(fn () => Installation::markInstalled());

it('refuses the installer without the install key', function () {
    $this->get(route('install.show'))->assertForbidden();
    $this->get(route('install.show', ['key' => 'wrong']))->assertForbidden();
});

it('refuses the installer once the lock file exists', function () {
    Installation::markInstalled();

    $this->get(route('install.show', ['key' => 'test-install-key']))->assertNotFound();
    $this->post(route('install.organization'), ['key' => 'test-install-key'])->assertNotFound();
});

it('refuses when no install key is configured at all', function () {
    config(['rentora.install.key' => '']);

    $this->get(route('install.show', ['key' => '']))->assertForbidden();
});

it('shows a not installed notice on other pages before install', function () {
    $this->get('/')->assertStatus(503)->assertSee(__('install.not_installed_title'));
    $this->get('/login')->assertStatus(503);
});

it('shows requirements and the organization step with a valid key', function () {
    $this->get(route('install.show', ['key' => 'test-install-key']))
        ->assertOk()
        ->assertSee(__('install.organization_title'));
});

it('creates the organization and owner, then writes the lock file', function () {
    $this->post(route('install.migrate'), ['key' => 'test-install-key'])->assertRedirect();

    $this->post(route('install.organization'), [
        'key' => 'test-install-key',
        'organization_name' => 'Adebayo Homes',
        'name' => 'Ade Owner',
        'email' => 'OWNER@example.com',
        'password' => 'Secret123',
        'password_confirmation' => 'Secret123',
    ])->assertRedirect(route('login'));

    $organization = Organization::query()->where('name', 'Adebayo Homes')->firstOrFail();
    $owner = User::query()->where('email', 'owner@example.com')->firstOrFail();

    expect($owner->role)->toBe(Role::Owner)
        ->and($owner->organization_id)->toBe($organization->id)
        ->and(Installation::isInstalled())->toBeTrue()
        ->and(Installation::installedVersion())->toBe(config('rentora.version'));
});

it('requires a strong owner password', function () {
    $this->post(route('install.organization'), [
        'key' => 'test-install-key',
        'organization_name' => 'Adebayo Homes',
        'name' => 'Ade Owner',
        'email' => 'owner@example.com',
        'password' => 'weak',
        'password_confirmation' => 'weak',
    ])->assertSessionHasErrors('password');

    expect(Installation::isInstalled())->toBeFalse();
});
