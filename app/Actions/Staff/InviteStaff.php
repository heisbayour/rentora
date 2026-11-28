<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\StaffInvitation;
use App\Services\ActivityLogger;
use App\Services\Messaging\MessagingConfig;
use App\Support\Delivery;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

/**
 * Creates a staff account and sends the invitation. The account is always
 * created and the link always returned: email is only tried when it is set
 * up, and a failing SMTP server is reported in the result, never thrown
 * (DECISIONS 104).
 */
class InviteStaff
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly MessagingConfig $messaging,
    ) {}

    /**
     * @param  array{name: string, email: string, phone?: ?string, role: string}  $data
     * @return array{user: User, link: string, delivery: Delivery}
     */
    public function handle(array $data): array
    {
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => Str::lower($data['email']),
            'phone' => $data['phone'] ?? null,
            'role' => Role::from($data['role']),
            'password' => Str::password(40),
            'is_active' => true,
        ]);
        // Created with a random password nobody knows: still waiting for the invite.
        $user->forceFill(['password_set_at' => null])->saveQuietly();

        $this->logger->log('staff.invited', $user, ['role' => $user->role->value]);
        [$link, $delivery] = $this->send($user);

        return ['user' => $user, 'link' => $link, 'delivery' => $delivery];
    }

    /**
     * Emails the invitation when email is set up and reports what happened.
     *
     * @return array{0: string, 1: Delivery}
     */
    public function send(User $user): array
    {
        $link = self::link($user);

        if (! $this->messaging->emailConfigured()) {
            return [$link, Delivery::notSetUp('email')];
        }

        try {
            $user->notify(new StaffInvitation($link));
        } catch (Throwable $e) {
            report($e);
            $this->logger->log('staff.invite_email_failed', $user, ['error' => mb_substr($e->getMessage(), 0, 300)]);

            return [$link, Delivery::failed('email', $e->getMessage())];
        }

        return [$link, Delivery::sent('email')];
    }

    /**
     * Signed for 7 days and bound to the current password hash, so the link
     * stops working as soon as a password has been set through it.
     */
    public static function link(User $user): string
    {
        return URL::temporarySignedRoute(
            'invite.staff',
            now()->addDays((int) config('rentora.links.invite_days')),
            ['user' => $user->getKey(), 'h' => self::fingerprint($user)],
        );
    }

    public static function fingerprint(User $user): string
    {
        return substr(hash_hmac('sha256', $user->getKey().'|'.$user->password, (string) config('app.key')), 0, 16);
    }
}
