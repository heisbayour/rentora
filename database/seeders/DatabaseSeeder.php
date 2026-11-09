<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use App\Services\Onboarding;
use App\Support\Installation;
use Illuminate\Database\Seeder;

/**
 * Local demo data (spec 14). Never run by the installer. Phase 13 expands
 * this with properties, tenants, invoices and maintenance history.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DefaultsSeeder::class);

        $organization = Organization::query()->firstOrCreate(['slug' => 'demo-properties'], [
            'name' => 'Demo Properties Ltd',
            'email' => 'hello@demo.test',
            'phone' => '08030000000',
            'address' => '12 Admiralty Way, Lekki, Lagos',
            'website_headline' => 'Quality homes in Lagos, Abuja and Minna',
        ]);

        foreach ([Role::Owner, Role::Manager, Role::Caretaker, Role::Tenant] as $role) {
            User::query()->firstOrCreate(['email' => $role->value.'@demo.test'], [
                'organization_id' => $organization->getKey(),
                'name' => 'Demo '.$role->label(),
                'password' => 'password',
                'role' => $role,
                'is_active' => true,
            ]);
        }

        Setting::query()->firstOrCreate(
            ['organization_id' => $organization->getKey(), 'key' => Onboarding::COMPLETED_KEY],
            ['value' => now()->toIso8601String()],
        );

        if (! Installation::isInstalled()) {
            Installation::markInstalled();
        }
    }
}
