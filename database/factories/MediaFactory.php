<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Media;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
 */
final class MediaFactory extends Factory
{
    protected $model = Media::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->slug(2);

        return [
            'tenant_id' => Tenant::factory(),
            'disk' => 'public',
            'directory' => 'media',
            'visibility' => 'public',
            'name' => $name,
            'path' => sprintf('media/%s.jpg', $name),
            'width' => 800,
            'height' => 600,
            'size' => 123456,
            'type' => 'image/jpeg',
            'ext' => 'jpg',
            'alt' => fake()->sentence(3),
        ];
    }
}
