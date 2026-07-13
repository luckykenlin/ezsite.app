<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Page;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Page>
 */
final class PageFactory extends Factory
{
    protected $model = Page::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'title' => fake()->sentence(),
            'slug' => fake()->unique()->slug(),
            'layout' => 'main',
            'blocks' => [],
        ];
    }
}
