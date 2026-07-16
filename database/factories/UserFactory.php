<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    private static ?string $password = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => self::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'is_super_admin' => false,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is a super admin with access to every panel and tenant.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_super_admin' => true,
        ]);
    }

    /**
     * Attach the user as a member of the given tenant once created.
     */
    public function memberOf(Tenant $tenant): static
    {
        return $this->afterCreating(function (User $user) use ($tenant): void {
            $user->tenants()->attach($tenant);
        });
    }
}
