<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\OutboxNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Finds recipients and sends notifications. A failure to notify is logged
 * and never undoes the money or workflow change that triggered it.
 */
class Notifier
{
    public function tenant(int $tenantId, OutboxNotification $notification): void
    {
        $tenant = Tenant::query()->withoutGlobalScopes()->find($tenantId);
        if ($tenant !== null) {
            $this->send(collect([$tenant]), $notification);
        }
    }

    /**
     * Active staff of an organization in the given roles (owners and managers by default).
     *
     * @param  list<Role>  $roles
     * @return Collection<int, User>
     */
    public function staff(int $organizationId, array $roles = [Role::Owner, Role::Manager], ?User $except = null): Collection
    {
        return User::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->whereIn('role', array_map(fn (Role $role) => $role->value, $roles))
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except?->getKey()))
            ->get();
    }

    /** @param  list<Role>  $roles */
    public function toStaff(int $organizationId, OutboxNotification $notification, array $roles = [Role::Owner, Role::Manager], ?User $except = null): void
    {
        $this->send($this->staff($organizationId, $roles, $except), $notification);
    }

    /** @param  Collection<int, covariant object>  $recipients */
    public function send(Collection $recipients, OutboxNotification $notification): void
    {
        if ($recipients->isEmpty()) {
            return;
        }
        try {
            Notification::send($recipients, $notification);
        } catch (Throwable $e) {
            Log::error('Notification failed', ['event' => $notification->event(), 'error' => $e->getMessage()]);
            if (app()->runningUnitTests()) {
                throw $e;
            }
        }
    }
}
