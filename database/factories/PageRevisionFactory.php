<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageRevision>
 */
final class PageRevisionFactory extends Factory
{
    protected $model = PageRevision::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'page_id' => fn (array $attributes): int => Page::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
            ])->id,
            'blocks' => [
                ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => fake()->sentence()]],
            ],
        ];
    }
}
