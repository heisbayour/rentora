<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\StaffInvitation;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class InviteStaff
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * @param  array{name: string, email: string, phone?: ?string, role: string}  $data
     */
    public function handle(array $data): User
    {
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => Str::lower($data['email']),
            'phone' => $data['phone'] ?? null,
            'role' => Role::from($data['role']),
            'password' => Str::password(40),
            'is_active' => true,
        ]);

        $this->send($user);
        $this->logger->log('staff.invited', $user, ['role' => $user->role->value]);

        return $user;
    }

    public function send(User $user): void
    {
        $user->notify(new StaffInvitation(self::link($user)));
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
