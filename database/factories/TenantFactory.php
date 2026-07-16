<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
final class TenantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'email' => fake()->unique()->safeEmail(),
        ];
    }

    /**
     * Give the tenant a domain once created. Pass a bare label ("acme") for a
     * subdomain or a dotted host ("acme.test") for a custom domain.
     */
    public function withDomain(string $domain): static
    {
        return $this->afterCreating(function (Tenant $tenant) use ($domain): void {
            $tenant->domains()->create(['domain' => $domain]);
        });
    }
}
