<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Business>
 */
final class BusinessFactory extends Factory
{
    protected $model = Business::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->company(),
            'category' => fake()->word(),
            'tagline' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'logo_path' => 'logos/'.fake()->uuid().'.png',
            'brand_primary' => fake()->hexColor(),
            'brand_secondary' => fake()->hexColor(),
            'brand_accent' => fake()->hexColor(),
            'contact_email' => fake()->companyEmail(),
            'contact_phone' => fake()->phoneNumber(),
            'website_url' => fake()->url(),
            'timezone' => fake()->timezone(),
            'locale' => 'en',
            'currency' => 'USD',
            'status' => 'draft',
        ];
    }
}
