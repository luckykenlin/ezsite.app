<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SiteSetting;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteSetting>
 */
final class SiteSettingFactory extends Factory
{
    protected $model = SiteSetting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'header' => null,
            'footer' => null,
        ];
    }

    /**
     * A saved header configuration in Fabricator block-entry shape.
     */
    public function withHeader(): self
    {
        return $this->state(fn (): array => [
            'header' => [[
                'type' => 'header',
                'data' => [
                    'variant' => 'centered',
                    'nav_links' => [['label' => 'Saved nav link', 'url' => '/about']],
                ],
            ]],
        ]);
    }

    /**
     * A saved footer configuration in Fabricator block-entry shape.
     */
    public function withFooter(): self
    {
        return $this->state(fn (): array => [
            'footer' => [[
                'type' => 'footer',
                'data' => [
                    'variant' => 'minimal',
                    'note' => 'Saved footer note',
                ],
            ]],
        ]);
    }
}
