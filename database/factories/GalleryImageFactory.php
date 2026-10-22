<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GalleryImage;
use App\Models\Media;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GalleryImage>
 */
final class GalleryImageFactory extends Factory
{
    protected $model = GalleryImage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'media_id' => fn (array $attributes): int|string => Media::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
            ])->id,
            'caption' => fake()->randomElement([
                'Milky white almond set with chrome',
                'Hand-painted cherry blossoms',
                'Short square French',
                'Glazed donut gel',
                'Tortoiseshell accents',
            ]),
            'catalog_category_id' => null,
            'sort' => 0,
        ];
    }
}
