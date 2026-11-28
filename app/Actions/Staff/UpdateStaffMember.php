<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Enums\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Changes a staff member's role or active flag (DECISIONS 105 and 107):
 * owners set owner, manager or caretaker; only a developer gives or takes
 * the developer role, and owners cannot demote or deactivate a developer.
 * Nobody changes their own role or deactivates themselves, and the last
 * active owner always stays an active owner.
 */
class UpdateStaffMember
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(User $actor, User $member, Role $role, bool $active): User
    {
        return DB::transaction(function () use ($actor, $member, $role, $active): User {
            $member = User::query()->lockForUpdate()->findOrFail($member->getKey());

            self::guard($actor, $member);
            if (! in_array($role, Role::assignableBy($actor->role), true)) {
                throw ValidationException::withMessages(['role' => __('staff.role_not_allowed')]);
            }

            if ($member->is($actor) && ($role !== $member->role || ! $active)) {
                throw ValidationException::withMessages(['role' => __('staff.cannot_demote_self')]);
            }

            $losesOwner = $member->role === Role::Owner && $member->is_active && ($role !== Role::Owner || ! $active);
            if ($losesOwner && self::activeOwnerCount() <= 1) {
                throw ValidationException::withMessages(['role' => __('staff.last_owner')]);
            }

            $previousRole = $member->role;
            $previousActive = $member->is_active;
            $member->forceFill(['role' => $role, 'is_active' => $active])->save();

            if ($previousRole !== $role) {
                $this->logger->log('staff.role_changed', $member, ['from' => $previousRole->value, 'to' => $role->value]);
            }
            if ($previousActive !== $active) {
                $this->logger->log($active ? 'staff.activated' : 'staff.deactivated', $member);
            }

            return $member;
        });
    }

    /** Only owners and developers manage staff; a developer account is managed by developers only. */
    public static function guard(User $actor, User $member): void
    {
        if (! $actor->isOwner() || ! $member->isStaff() || $member->removed_at !== null) {
            abort(404);
        }
        if ($member->isDeveloper() && ! $actor->isDeveloper()) {
            throw ValidationException::withMessages(['role' => __('staff.developer_protected')]);
        }
    }

    /** Active owners, developers not counted: the client must always keep a real owner. */
    public static function activeOwnerCount(): int
    {
        return User::query()->where('role', Role::Owner->value)->where('is_active', true)->whereNull('removed_at')->lockForUpdate()->count();
    }
}
