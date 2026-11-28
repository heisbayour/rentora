<?php

declare(strict_types=1);

use App\Actions\Tenants\InviteTenant;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\OutboundMessage;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\StaffInvitation;
use App\Services\Messaging\DirectMail;
use App\Services\Messaging\Outbox;
use App\Services\SettingsService;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Mailer\Exception\TransportException;

/*
 * v1.0.4 rule (DECISIONS 104): no invite, resend or password reset may fail
 * because email or SMS is missing or broken. Each path runs with email off,
 * with the mail transport throwing and with SMS failing, and must not 500,
 * must keep the user, and must show the link.
 */

/** The live .env: MAIL_MAILER=smtp with an empty host. */
function emailOff(): void
{
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '']);
}

/** An SMTP server that refuses every connection. */
function mailTransportThrows(): void
{
    test()->mock(DirectMail::class, fn ($mock) => $mock->shouldReceive('send')
        ->andThrow(new TransportException('Connection could not be established with host smtp.example.test')));
}

/** Termii selected and keyed, but every call fails. */
function smsFailing(User $owner): void
{
    app(CurrentOrganization::class)->runAs($owner->organization, fn () => app(SettingsService::class)->setMany([
        'sms.driver' => 'termii', 'sms.termii_api_key' => 'TLkey1234', 'sms.termii_sender_id' => 'Rentora',
    ], ['sms.termii_api_key']));
    Http::fake(['*' => Http::response(['message' => 'Insufficient balance'], 500)]);
}

function inviteStaffMember(User $owner): TestResponse
{
    return test()->actingAs($owner)->post(route('app.staff.store'), [
        'name' => 'Musa Bello', 'email' => 'musa@example.com', 'phone' => '08031234567', 'role' => 'caretaker',
    ]);
}

dataset('broken email', [
    'email off' => [fn () => emailOff(), 'Email is not set up, share the link yourself.'],
    'mail transport throwing' => [fn () => mailTransportThrows(), 'Email could not be sent'],
]);

it('creates the staff account and shows the link when email is broken', function (Closure $break, string $says) {
    $owner = userWithRole(Role::Owner);
    $break();

    inviteStaffMember($owner)->assertRedirect(route('app.staff.index'))->assertSessionHasNoErrors();

    $member = User::query()->where('email', 'musa@example.com')->firstOrFail();
    $share = session('invite_share');
    expect($member->role)->toBe(Role::Caretaker)
        ->and($member->awaitingPassword())->toBeTrue()
        ->and($share['link'])->toContain('/invite/staff/'.$member->id)
        ->and($share['deliveries'][0]['ok'])->toBeFalse()
        ->and($share['deliveries'][0]['message'])->toContain($says)
        ->and($share['sms'])->toStartWith('sms:+2348031234567?body=')
        ->and($share['mailto'])->toStartWith('mailto:musa@example.com?subject=');

    $this->get(route('app.staff.index'))->assertOk()
        ->assertSee($share['link'])
        ->assertSee($says)
        ->assertSee(__('ui.invite_share.copy'))
        ->assertSee(__('ui.invite_share.sms'))
        ->assertSee(__('ui.invite_share.email'))
        ->assertSee(__('staff.get_invite_link'));
})->with('broken email');

it('resends a staff invite without a 500 when email is broken', function (Closure $break, string $says) {
    $owner = userWithRole(Role::Owner);
    emailOff();
    inviteStaffMember($owner);
    $member = User::query()->where('email', 'musa@example.com')->firstOrFail();
    config(['mail.default' => 'array']);
    $break();

    $this->post(route('app.staff.resend', $member))->assertRedirect(route('app.staff.index'))
        ->assertSessionHas('invite_share', fn (array $share) => str_contains($share['link'], '/invite/staff/')
            && str_contains($share['deliveries'][0]['message'], $says));
})->with('broken email');

it('sends the staff invite by email when email works', function () {
    Notification::fake();
    $owner = userWithRole(Role::Owner);

    inviteStaffMember($owner);

    $member = User::query()->where('email', 'musa@example.com')->firstOrFail();
    Notification::assertSentTo($member, StaffInvitation::class);
    expect(session('invite_share')['deliveries'][0])->toBe(['ok' => true, 'message' => __('ui.share.delivery.email.sent')]);
});

it('issues a fresh invite link for staff who have not set a password, and the link works in a new session', function () {
    $owner = userWithRole(Role::Owner);
    emailOff();
    inviteStaffMember($owner);
    $member = User::query()->where('email', 'musa@example.com')->firstOrFail();

    $this->post(route('app.staff.invite-link', $member))->assertRedirect(route('app.staff.index'));
    $link = session('invite_share')['link'];
    expect(session('invite_share')['deliveries'])->toBe([])
        ->and(ActivityLog::query()->where('action', 'staff.invite_link_issued')->where('subject_id', $member->id)->exists())->toBeTrue();
    $this->get(route('app.staff.index'))->assertSee(__('ui.invite_share.not_sent'));

    auth()->logout();
    $this->flushSession();
    $this->get($link)->assertOk()->assertSee('musa@example.com');
    $this->post($link, ['password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])->assertRedirect(route('app.dashboard'));
    expect($member->fresh()->awaitingPassword())->toBeFalse();

    // Once the password is set the row offers a reset link instead.
    $this->actingAs($owner)->post(route('app.staff.invite-link', $member))->assertSessionHasErrors('member');
    $this->get(route('app.staff.index'))->assertDontSee(route('app.staff.invite-link', $member));
});

it('answers forgot password with a clear message instead of an error when email is broken', function (Closure $break, string $message) {
    $user = userWithRole(Role::Manager, null, ['email' => 'chika@example.com']);
    $break();

    $this->from(route('password.request'))->post(route('password.email'), ['email' => 'chika@example.com'])
        ->assertRedirect(route('password.request'))
        ->assertSessionHasErrors(['email' => $message]);

    expect($user->fresh())->not->toBeNull();
})->with([
    'email off' => [fn () => emailOff(), 'Email is not set up. Ask the property owner for a reset link.'],
    'mail transport throwing' => [fn () => mailTransportThrows(), 'The reset email could not be sent right now. Ask the property owner for a reset link.'],
]);

it('still emails the reset link when email works', function () {
    Notification::fake();
    $user = userWithRole(Role::Manager, null, ['email' => 'chika@example.com']);

    $this->post(route('password.email'), ['email' => 'chika@example.com'])->assertSessionHasNoErrors();

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

it('lets the owner get a password reset link for a staff member, which resets the password', function () {
    emailOff();
    $owner = userWithRole(Role::Owner);
    $member = userWithRole(Role::Manager, $owner->organization, ['email' => 'chika@example.com']);

    $this->actingAs($owner)->post(route('app.staff.reset-link', $member))->assertRedirect(route('app.staff.index'));
    $share = session('invite_share');
    expect($share['kind'])->toBe('reset')
        ->and($share['link'])->toContain('/reset-password/')
        ->and(ActivityLog::query()->where('action', 'user.reset_link_issued')->where('subject_id', $member->id)->exists())->toBeTrue();
    $this->get(route('app.staff.index'))->assertSee(__('ui.invite_share.reset_title', ['name' => $member->name]));

    auth()->logout();
    $this->get($share['link'])->assertOk();
    $token = basename((string) parse_url($share['link'], PHP_URL_PATH));
    $this->post(route('password.update'), [
        'token' => $token, 'email' => 'chika@example.com', 'password' => 'Fresh-Pass-99', 'password_confirmation' => 'Fresh-Pass-99',
    ])->assertSessionHasNoErrors();

    expect(Hash::check('Fresh-Pass-99', $member->fresh()->password))->toBeTrue();
});

it('lets the owner get a password reset link for a tenant portal account', function () {
    emailOff();
    $owner = userWithRole(Role::Owner);
    $account = userWithRole(Role::Tenant, $owner->organization, ['email' => 'ada@example.com']);
    $tenant = Tenant::factory()->create(['organization_id' => $owner->organization_id, 'email' => 'ada@example.com', 'user_id' => $account->id]);

    $this->actingAs($owner)->from(route('app.tenants.show', $tenant))->post(route('app.tenants.reset-link', $tenant))
        ->assertRedirect(route('app.tenants.show', $tenant));

    $link = session('invite_share')['link'];
    expect(Password::broker()->tokenExists($account, basename((string) parse_url($link, PHP_URL_PATH))))->toBeTrue();
    $this->get(route('app.tenants.show', $tenant))->assertOk()->assertSee($link)->assertSee(__('tenants.get_reset_link'));
});

it('only lets owners and developers issue reset links, never an owner for a developer or anyone for themselves', function () {
    $owner = userWithRole(Role::Owner);
    $manager = userWithRole(Role::Manager, $owner->organization);
    $developer = userWithRole(Role::Developer, $owner->organization);
    $caretaker = userWithRole(Role::Caretaker, $owner->organization);
    $tenant = Tenant::factory()->create(['organization_id' => $owner->organization_id, 'user_id' => userWithRole(Role::Tenant, $owner->organization)->id]);

    $this->actingAs($manager)->post(route('app.staff.reset-link', $caretaker))->assertForbidden();
    $this->actingAs($manager)->post(route('app.tenants.reset-link', $tenant))->assertForbidden();
    $this->actingAs($owner)->post(route('app.staff.reset-link', $developer))->assertSessionHasErrors('role');
    $this->actingAs($owner)->post(route('app.staff.reset-link', $owner))->assertSessionHasErrors('member');
    $this->actingAs($developer)->post(route('app.staff.reset-link', $owner))->assertSessionHas('invite_share');
});

it('invites a tenant without a 500 when email is off and SMS fails, and the outbox records the failure', function () {
    $owner = userWithRole(Role::Owner);
    emailOff();
    smsFailing($owner);
    $tenant = Tenant::factory()->create(['organization_id' => $owner->organization_id, 'email' => 'ada@example.com', 'phone' => '+2348031234567']);

    $this->actingAs($owner)->from(route('app.tenants.show', $tenant))->post(route('app.tenants.invite', $tenant))
        ->assertRedirect(route('app.tenants.show', $tenant))
        ->assertSessionHasNoErrors();

    $share = session('invite_share');
    expect(strtok($share['link'], '?'))->toBe(strtok(InviteTenant::link($tenant->fresh()), '?'))
        ->and(array_column($share['deliveries'], 'message'))->toBe([
            __('ui.share.delivery.email.not_set_up'),
            __('ui.share.delivery.sms.queued'),
        ]);
    $this->get(route('app.tenants.show', $tenant))->assertOk()->assertSee($share['link']);

    // The scheduler later fails to send both, without throwing: the email at
    // once (no SMTP server), the SMS is retried after Termii's error.
    $result = app(Outbox::class)->drain();
    expect($result)->toBe(['sent' => 0, 'failed' => 1, 'retried' => 1])
        ->and(OutboundMessage::query()->where('channel', 'email')->value('last_error'))->toBe(__('messaging.errors.email_not_set_up'))
        ->and(OutboundMessage::query()->where('channel', 'sms')->value('last_error'))->toContain('Insufficient balance');
});

it('says SMS is not set up when no SMS provider is configured', function () {
    $owner = userWithRole(Role::Owner);
    $tenant = Tenant::factory()->create(['organization_id' => $owner->organization_id, 'email' => null, 'phone' => '+2348031234567']);

    $this->actingAs($owner)->post(route('app.tenants.invite', $tenant));

    expect(array_column(session('invite_share')['deliveries'], 'message'))->toBe([
        __('ui.share.delivery.email.no_address'),
        __('ui.share.delivery.sms.not_set_up'),
    ]);
});

it('issues a fresh tenant invite link that replaces the old one', function () {
    $manager = userWithRole(Role::Manager);
    $tenant = Tenant::factory()->create(['organization_id' => $manager->organization_id, 'invite_sent_at' => now()->subDay()]);
    $old = InviteTenant::link($tenant);

    $this->actingAs($manager)->from(route('app.tenants.show', $tenant))->post(route('app.tenants.invite-link', $tenant))->assertRedirect();
    $new = session('invite_share')['link'];
    expect(session('invite_share')['deliveries'])->toBe([]);

    auth()->logout();
    $this->get($old)->assertForbidden();
    $this->get($new)->assertOk();
});
