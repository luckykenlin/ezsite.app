<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PhotoCategory;
use App\Models\LibraryPhoto;
use App\StockPhotos\PhotoOrientation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LibraryPhoto>
 */
final class LibraryPhotoFactory extends Factory
{
    protected $model = LibraryPhoto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->slug(2);
        $alt = fake()->sentence(4);

        return [
            'provider' => 'pexels',
            'source_id' => (string) fake()->unique()->numberBetween(1, 10_000_000),
            'source_url' => 'https://www.pexels.com/photo/'.fake()->numberBetween(1, 10_000_000).'/',
            'photographer_name' => fake()->name(),
            'photographer_url' => 'https://www.pexels.com/@'.fake()->userName(),
            'disk' => 'library',
            'path' => sprintf('photos/%s.jpg', $name),
            'name' => $name,
            'ext' => 'jpg',
            'type' => 'image/jpeg',
            'size' => 123456,
            'width' => 1920,
            'height' => 1280,
            'orientation' => PhotoOrientation::Landscape,
            'alt' => $alt,
            'category' => PhotoCategory::Interior,
            'tags' => ['interior', 'warm'],
            // `keywords` is deliberately unset — LibraryPhoto derives it from
            // these fields on save.
            'search_query' => 'cafe interior',
            'palette' => ['#c08040', '#402010'],
            'dominant_color' => '#c08040',
            'is_dark' => false,
            'usage_count' => 0,
            'published_at' => now(),
        ];
    }

    /**
     * Curated out of the library: still catalogued (so it is not re-imported)
     * but never offered by a search again.
     */
    public function unpublished(): self
    {
        return $this->state(fn (): array => ['published_at' => null]);
    }

    /**
     * A photo dark enough to carry overlaid text, i.e. usable as a full-bleed
     * hero background.
     */
    public function dark(): self
    {
        return $this->state(fn (): array => [
            'is_dark' => true,
            'palette' => ['#1a1a22', '#33333f'],
            'dominant_color' => '#1a1a22',
        ]);
    }

    /**
     * Describe the photo with EXACTLY these words: the alt text carries them,
     * and everything else that feeds the derived `keywords` column is cleared,
     * so a `matching()` assertion can be precise about what does and does not
     * hit.
     */
    public function describing(string $description): self
    {
        return $this->state(fn (): array => [
            'alt' => $description,
            'search_query' => null,
            'category' => null,
            'tags' => [],
        ]);
    }
}
