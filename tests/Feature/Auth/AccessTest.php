<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\ActivityLog;

it('redirects guests to login', function (string $path) {
    $this->get($path)->assertRedirect(route('login'));
})->with(['/app', '/portal', '/app/staff']);

it('sends a tenant who opens a console page to the portal', function (string $path) {
    $this->actingAs(userWithRole(Role::Tenant))->get($path)->assertRedirect(route('portal.home'));
})->with(['/app', '/app/staff', '/app/invoices']);

it('sends staff who open a portal page to the console', function (Role $role, string $path) {
    $this->actingAs(userWithRole($role))->get($path)->assertRedirect(route('app.dashboard'));
})->with([Role::Owner, Role::Manager, Role::Caretaker])->with(['/portal', '/portal/pay', '/portal/invoices']);

it('still forbids changes in the other area', function () {
    $this->actingAs(userWithRole(Role::Tenant))->post('/app/properties', ['name' => 'X'])->assertForbidden();
    $this->actingAs(userWithRole(Role::Owner))->post('/portal/repairs', [])->assertForbidden();
    $this->actingAs(userWithRole(Role::Owner))->getJson('/portal')->assertForbidden();
});

it('sends each role from /start to its own home', function (Role $role, string $home) {
    $this->actingAs(userWithRole($role))->get('/start')->assertRedirect(route($home));
})->with([
    [Role::Owner, 'app.dashboard'],
    [Role::Manager, 'app.dashboard'],
    [Role::Caretaker, 'app.dashboard'],
    [Role::Tenant, 'portal.home'],
]);

it('sends guests from /start to the login page', function () {
    $this->get('/start')->assertRedirect(route('login'));
});

it('lets an owner remembered by cookie reopen the app on /start and /portal', function () {
    $owner = userWithRole(Role::Owner);
    $owner->forceFill(['remember_token' => 'remember-me-token'])->save();

    // A new app start: no session, only the "Remember me" cookie.
    $name = auth()->guard('web')->getRecallerName();
    $value = $owner->id.'|remember-me-token|'.$owner->getAuthPassword();

    $this->withCookie($name, $value)->get('/start')->assertRedirect(route('app.dashboard'));
    app('auth')->forgetGuards();
    $this->flushSession();
    $this->withCookie($name, $value)->get('/portal')->assertRedirect(route('app.dashboard'));
});

it('lets each role into its own area', function (Role $role, string $path) {
    $this->actingAs(userWithRole($role))->get($path)->assertOk();
})->with([
    [Role::Owner, '/app'],
    [Role::Manager, '/app'],
    [Role::Caretaker, '/app'],
    [Role::Tenant, '/portal'],
]);

it('keeps managers and caretakers out of owner-only staff management', function (Role $role) {
    $this->actingAs(userWithRole($role))->get(route('app.staff.index'))->assertForbidden();
})->with([Role::Manager, Role::Caretaker]);

it('sends staff to /app and tenants to /portal after login', function (Role $role, string $home) {
    $user = userWithRole($role);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect($home);
})->with([
    [Role::Owner, '/app'],
    [Role::Caretaker, '/app'],
    [Role::Tenant, '/portal'],
]);

it('ignores an intended URL from the other area', function () {
    $tenant = userWithRole(Role::Tenant);

    $this->get('/app/staff');
    $this->post(route('login'), ['email' => $tenant->email, 'password' => 'password'])
        ->assertRedirect('/portal');
});

it('records the login time and an audit entry', function () {
    $user = userWithRole(Role::Manager);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

    expect($user->fresh()->last_login_at)->not->toBeNull()
        ->and(ActivityLog::query()->where('action', 'auth.login')->where('user_id', $user->id)->exists())->toBeTrue();
});

it('rejects an inactive user at login with a clear message', function () {
    $user = userWithRole(Role::Manager, attributes: ['is_active' => false]);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => __('auth.inactive')]);
    $this->assertGuest();
});

it('signs out a user deactivated while logged in', function () {
    $user = userWithRole(Role::Manager);
    $this->actingAs($user);
    $user->forceFill(['is_active' => false])->save();

    $this->get('/app')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('throttles login after 5 attempts per minute per email and IP', function () {
    $user = userWithRole(Role::Manager);

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
    }

    $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong'])->assertStatus(429);
});

it('offers two-factor authentication to owners and managers only', function (Role $role, bool $allowed) {
    $user = userWithRole($role);

    $response = $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('two-factor.enable'));

    $allowed ? $response->assertRedirect() : $response->assertForbidden();
    expect($user->fresh()->two_factor_secret !== null)->toBe($allowed);
})->with([
    [Role::Owner, true],
    [Role::Manager, true],
    [Role::Caretaker, false],
    [Role::Tenant, false],
]);

it('lets staff update their profile', function () {
    $user = userWithRole(Role::Manager);

    $this->actingAs($user)->put(route('app.profile.update'), ['name' => 'New Name', 'phone' => '08031234567'])
        ->assertRedirect(route('app.profile.edit'));

    expect($user->fresh())->name->toBe('New Name')->phone->toBe('08031234567');
});

it('requires mixed case and numbers for staff passwords', function () {
    $user = userWithRole(Role::Manager);

    $this->actingAs($user)->put(route('user-password.update'), [
        'current_password' => 'password',
        'password' => 'alllowercase',
        'password_confirmation' => 'alllowercase',
    ])->assertSessionHasErrorsIn('updatePassword', 'password');
});
