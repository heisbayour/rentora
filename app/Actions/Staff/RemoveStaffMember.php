<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Enums\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Removes a staff account (DECISIONS 107). An account with history (audit
 * entries it made, payments it recorded, repairs, uploads, announcements)
 * is deactivated and anonymised so the history keeps pointing at a row;
 * an account with no history at all is deleted. Nobody removes themselves,
 * owners cannot remove a developer, and the last active owner stays.
 */
class RemoveStaffMember
{
    /** Columns that point at users and make up an account's history. */
    private const HISTORY = [
        ['activity_logs', 'user_id'],
        ['payments', 'recorded_by'],
        ['payments', 'approved_by'],
        ['maintenance_requests', 'reported_by_user_id'],
        ['maintenance_requests', 'assigned_user_id'],
        ['maintenance_attachments', 'uploaded_by_user_id'],
        ['maintenance_updates', 'user_id'],
        ['expenses', 'recorded_by'],
        ['documents', 'uploaded_by'],
        ['announcements', 'created_by'],
    ];

    public function __construct(private readonly ActivityLogger $logger) {}

    /** @return 'deleted'|'anonymised' */
    public function handle(User $actor, User $member): string
    {
        return DB::transaction(function () use ($actor, $member): string {
            $member = User::query()->lockForUpdate()->findOrFail($member->getKey());

            UpdateStaffMember::guard($actor, $member);
            if ($member->is($actor)) {
                throw ValidationException::withMessages(['member' => __('staff.cannot_remove_self')]);
            }
            if ($member->role === Role::Owner && $member->is_active && UpdateStaffMember::activeOwnerCount() <= 1) {
                throw ValidationException::withMessages(['member' => __('staff.last_owner')]);
            }

            $details = ['name' => $member->name, 'role' => $member->role->value];
            $mode = $this->hasHistory($member) ? 'anonymised' : 'deleted';

            DB::table('sessions')->where('user_id', $member->getKey())->delete();
            DB::table('password_reset_tokens')->where('email', $member->email)->delete();
            DB::table('notifications')->where('notifiable_type', $member->getMorphClass())->where('notifiable_id', $member->getKey())->delete();

            if ($mode === 'deleted') {
                $this->logger->log('staff.removed', $member, $details + ['mode' => $mode]);
                $member->delete();

                return $mode;
            }

            $member->forceFill([
                'name' => __('staff.removed_name'),
                'email' => 'removed-'.$member->getKey().'-'.Str::lower(Str::random(8)).'@removed.invalid',
                'phone' => null,
                'password' => Str::password(40),
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'remember_token' => null,
                'is_active' => false,
                'removed_at' => now(),
            ])->save();
            $this->logger->log('staff.removed', $member, $details + ['mode' => $mode]);

            return $mode;
        });
    }

    private function hasHistory(User $member): bool
    {
        foreach (self::HISTORY as [$table, $column]) {
            if (DB::table($table)->where($column, $member->getKey())->exists()) {
                return true;
            }
        }

        return false;
    }
}
