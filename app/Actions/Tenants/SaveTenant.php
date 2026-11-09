<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Models\Tenant;
use Illuminate\Support\Str;

class SaveTenant
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?Tenant $tenant, array $data): Tenant
    {
        $tenant ??= new Tenant(['notification_prefs' => Tenant::DEFAULT_PREFS]);
        if (isset($data['email']) && is_string($data['email'])) {
            $data['email'] = Str::lower(trim($data['email'])) ?: null;
        }
        $tenant->fill($data);
        $tenant->save();

        // Keep the portal login in step with the tenant's name and email.
        if ($tenant->user !== null) {
            $tenant->user->fill(array_filter([
                'name' => $tenant->full_name,
                'phone' => $tenant->phone,
                'email' => $tenant->email,
            ]))->save();
        }

        return $tenant;
    }
}
