<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LinkKind;
use App\Models\BusinessLink;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessLink>
 */
final class BusinessLinkFactory extends Factory
{
    protected $model = BusinessLink::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'kind' => LinkKind::Instagram,
            'url' => 'https://www.instagram.com/'.fake()->userName(),
            'sort' => 0,
        ];
    }

    /**
     * The salon's Booksy page — the commonest "Book now" target.
     */
    public function booksy(): self
    {
        return $this->state(fn (): array => [
            'kind' => LinkKind::Booksy,
            'url' => 'https://booksy.com/en-us/'.fake()->numberBetween(100000, 999999).'_'.fake()->slug(2),
        ]);
    }
}
