<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Enums\LeaseStatus;
use App\Enums\Role;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Creates the tenant's portal login from an invitation. */
class AcceptTenantInvitation
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(Tenant $tenant, string $email, string $password): User
    {
        return DB::transaction(function () use ($tenant, $email, $password): User {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());
            $email = Str::lower(trim($email));

            $user = User::query()->create([
                'organization_id' => $tenant->organization_id,
                'name' => $tenant->full_name,
                'email' => $email,
                'phone' => $tenant->phone,
                'password' => $password,
                'role' => Role::Tenant,
                'is_active' => true,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            $hasActiveLease = $tenant->leases()->where('status', LeaseStatus::Active->value)->exists();
            $tenant->forceFill([
                'user_id' => $user->getKey(),
                'email' => $tenant->email ?? $email,
                'status' => $hasActiveLease || $tenant->status === TenantStatus::PendingInvite ? TenantStatus::Active : $tenant->status,
            ])->save();

            $this->logger->log('tenant.activated', $tenant, [], $user);

            return $user;
        });
    }
}
