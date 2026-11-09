<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use App\Services\Onboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/** An organization whose first-run wizard is already finished. */
function organization(array $attributes = []): Organization
{
    $organization = Organization::factory()->create($attributes);
    Setting::query()->create([
        'organization_id' => $organization->getKey(),
        'key' => Onboarding::COMPLETED_KEY,
        'value' => now()->toIso8601String(),
    ]);

    return $organization;
}

/** A user with the given role in the given (or a new) organization. */
function userWithRole(Role $role, ?Organization $organization = null, array $attributes = []): User
{
    return User::factory()->role($role)->create([
        'organization_id' => ($organization ?? organization())->getKey(),
        ...$attributes,
    ]);
}
