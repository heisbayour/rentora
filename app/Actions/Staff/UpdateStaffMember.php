<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Enums\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Changes a staff member's role or active flag, keeping at least one active owner. */
class UpdateStaffMember
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(User $actor, User $member, Role $role, bool $active): User
    {
        return DB::transaction(function () use ($actor, $member, $role, $active): User {
            $member = User::query()->lockForUpdate()->findOrFail($member->getKey());

            if ($member->is($actor) && ($role !== Role::Owner || ! $active)) {
                throw ValidationException::withMessages(['role' => __('staff.cannot_demote_self')]);
            }

            $losesOwner = $member->role === Role::Owner && $member->is_active && ($role !== Role::Owner || ! $active);
            if ($losesOwner && $this->activeOwnerCount() <= 1) {
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

    private function activeOwnerCount(): int
    {
        return User::query()->where('role', Role::Owner->value)->where('is_active', true)->lockForUpdate()->count();
    }
}
