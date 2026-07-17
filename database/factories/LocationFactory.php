<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\Location;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\OpeningHours\OpeningHours;

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
            'opening_hours' => OpeningHours::create([
                'monday' => ['09:00-17:00'],
                'tuesday' => ['09:00-17:00'],
                'wednesday' => ['09:00-17:00'],
                'thursday' => ['09:00-17:00'],
                'friday' => ['09:00-17:00'],
                'saturday' => ['10:00-14:00'],
                'sunday' => [],
            ]),
            'status' => 'active',
        ];
    }

    /**
     * A location open around the clock, every day.
     */
    public function alwaysOpen(): self
    {
        return $this->state(fn (): array => [
            'opening_hours' => OpeningHours::create([
                'monday' => ['00:00-24:00'],
                'tuesday' => ['00:00-24:00'],
                'wednesday' => ['00:00-24:00'],
                'thursday' => ['00:00-24:00'],
                'friday' => ['00:00-24:00'],
                'saturday' => ['00:00-24:00'],
                'sunday' => ['00:00-24:00'],
            ]),
        ]);
    }

    /**
     * A location with no opening hours recorded.
     */
    public function withoutOpeningHours(): self
    {
        return $this->state(fn (): array => ['opening_hours' => null]);
    }
}
