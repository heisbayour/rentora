<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '0803'.fake()->numerify('#######'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => Role::Manager,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function role(Role $role): static
    {
        return $this->state(fn (array $attributes) => ['role' => $role]);
    }

    public function owner(): static
    {
        return $this->role(Role::Owner);
    }

    public function manager(): static
    {
        return $this->role(Role::Manager);
    }

    public function caretaker(): static
    {
        return $this->role(Role::Caretaker);
    }

    public function tenant(): static
    {
        return $this->role(Role::Tenant);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
