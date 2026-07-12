<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Domain;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Domain>
 */
final class DomainFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'domain' => fake()->unique()->domainWord(),
            'tenant_id' => Tenant::factory(),
        ];
    }
}
