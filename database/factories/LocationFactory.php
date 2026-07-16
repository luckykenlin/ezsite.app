<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\Location;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
final class LocationFactory extends Factory
{
    protected $model = Location::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'business_id' => fn (array $attributes): int => Business::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
            ])->id,
            'label' => fake()->city(),
            'is_primary' => false,
            'address_line1' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->randomElement(['CA', 'NY', 'TX', 'FL', 'WA', 'IL']),
            'postal_code' => fake()->postcode(),
            'country' => fake()->countryCode(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'timezone' => fake()->timezone(),
            'status' => 'active',
        ];
    }
}
