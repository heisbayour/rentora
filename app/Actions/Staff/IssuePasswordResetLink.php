<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * "Get password reset link" (DECISIONS 106): an owner or developer issues the
 * normal Fortify reset link for a staff member or tenant who forgot their
 * password while email is off. Nothing is sent; the link is shown to share.
 * Owners cannot do this for a developer, and nobody for themselves.
 */
class IssuePasswordResetLink
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(User $actor, User $target): string
    {
        if (! $actor->isOwner()) {
            abort(403);
        }
        if ($target->is($actor)) {
            throw ValidationException::withMessages(['member' => __('staff.reset_link_self')]);
        }
        if ($target->isDeveloper() && ! $actor->isDeveloper()) {
            throw ValidationException::withMessages(['member' => __('staff.developer_protected')]);
        }
        if (! $target->is_active || $target->removed_at !== null) {
            throw ValidationException::withMessages(['member' => __('staff.reset_link_inactive')]);
        }

        $broker = Password::broker();
        assert($broker instanceof PasswordBroker);
        $token = $broker->createToken($target);

        $this->logger->log('user.reset_link_issued', $target, ['role' => $target->role->value]);

        return route('password.reset', ['token' => $token, 'email' => $target->email]);
    }
}
