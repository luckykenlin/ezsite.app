<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Vertical;
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

    /**
     * A site for one kind of business. Tenants have no vertical by default,
     * matching the column and every pre-pivot template site.
     */
    public function vertical(Vertical $vertical): static
    {
        return $this->state(fn (): array => ['vertical' => $vertical]);
    }
}
