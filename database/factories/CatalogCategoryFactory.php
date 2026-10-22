<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CatalogCategory;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogCategory>
 */
final class CatalogCategoryFactory extends Factory
{
    protected $model = CatalogCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            // unique(): the (tenant_id, name) index rejects a repeat, and two
            // categories made for one tenant in a test must not trip it.
            'name' => fake()->unique()->randomElement([
                'Manicures', 'Pedicures', 'Acrylics', 'Gel-X & Extensions', 'Dip powder',
                'Nail art', 'Waxing', 'Kids', 'Add-ons', 'Lashes & Brows',
            ]),
            'description' => fake()->optional()->sentence(),
            'sort' => 0,
        ];
    }
}
