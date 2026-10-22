<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CatalogCategory;
use App\Models\CatalogItem;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogItem>
 */
final class CatalogItemFactory extends Factory
{
    protected $model = CatalogItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $services = [
            ['Classic manicure', 2500, 30],
            ['Gel manicure', 4500, 45],
            ['Spa pedicure', 5000, 50],
            ['Gel pedicure', 5500, 60],
            ['Dip powder manicure', 5000, 60],
            ['Gel-X full set', 7000, 90],
            ['Gel polish removal', 1000, 15],
        ];
        [$name, $priceCents, $durationMinutes] = $services[fake()->numberBetween(0, count($services) - 1)];

        return [
            'tenant_id' => Tenant::factory(),
            'catalog_category_id' => fn (array $attributes): int => CatalogCategory::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
            ])->id,
            'name' => $name,
            'description' => fake()->sentence(),
            'price_cents' => $priceCents,
            'price_is_from' => false,
            'duration_minutes' => $durationMinutes,
            'add_ons' => [],
            'tags' => [],
            'is_featured' => false,
            'sort' => 0,
        ];
    }

    /**
     * The priced-from service every salon lists: the floor is fixed, length and
     * shape move it up.
     */
    public function fromPrice(): self
    {
        return $this->state(fn (): array => [
            'name' => 'Acrylic full set',
            'price_cents' => 6500,
            'price_is_from' => true,
            'duration_minutes' => 90,
        ]);
    }

    /**
     * Custom work quoted in person — no price on the menu.
     */
    public function askForPrice(): self
    {
        return $this->state(fn (): array => [
            'name' => 'Custom nail art',
            'price_cents' => null,
            'price_is_from' => false,
        ]);
    }

    /**
     * With the extras a salon upsells at the chair.
     */
    public function withAddOns(): self
    {
        return $this->state(fn (): array => [
            'add_ons' => [
                ['name' => 'French tip', 'price_cents' => 1000],
                ['name' => 'Chrome powder', 'price_cents' => 1500],
            ],
        ]);
    }

    public function featured(): self
    {
        return $this->state(fn (): array => ['is_featured' => true]);
    }
}
