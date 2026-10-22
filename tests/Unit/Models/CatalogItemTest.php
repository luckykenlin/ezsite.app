<?php

declare(strict_types=1);

use App\Models\CatalogCategory;
use App\Models\CatalogItem;
use App\Models\Media;
use App\Models\Tenant;

test('tenant relation returns the owning tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $item = $this->runInTenant($tenant, fn (): CatalogItem => CatalogItem::factory()->create(['tenant_id' => $tenant->id]));
    $item = CatalogItem::query()->findOrFail($item->getKey());

    expect($item->tenant->is($tenant))->toBeTrue();
});

test('category relation returns the owning category', function (): void {
    $tenant = Tenant::factory()->create();
    [$itemId, $categoryId] = $this->runInTenant($tenant, function () use ($tenant): array {
        $category = CatalogCategory::factory()->create(['tenant_id' => $tenant->id]);
        $item = CatalogItem::factory()->create(['tenant_id' => $tenant->id, 'catalog_category_id' => $category->id]);

        return [$item->id, $category->id];
    });

    expect(CatalogItem::query()->findOrFail($itemId)->category->getKey())->toBe($categoryId);
});

test('media relation returns the item photo', function (): void {
    $tenant = Tenant::factory()->create();
    [$itemId, $mediaId] = $this->runInTenant($tenant, function () use ($tenant): array {
        $media = Media::factory()->create(['tenant_id' => $tenant->id]);
        $item = CatalogItem::factory()->create(['tenant_id' => $tenant->id, 'media_id' => $media->id]);

        return [$item->id, $media->id];
    });

    expect(CatalogItem::query()->findOrFail($itemId)->media->getKey())->toBe($mediaId);
});

test('deleting the photo leaves the item without one', function (): void {
    $tenant = Tenant::factory()->create();
    $itemId = $this->runInTenant($tenant, function () use ($tenant): int {
        $media = Media::factory()->create(['tenant_id' => $tenant->id]);
        $item = CatalogItem::factory()->create(['tenant_id' => $tenant->id, 'media_id' => $media->id]);

        $media->delete();

        return $item->id;
    });

    expect(CatalogItem::query()->findOrFail($itemId)->media_id)->toBeNull();
});

test('a freshly created item reads empty add-ons and tags without a re-fetch', function (): void {
    // The PHP-side defaults: a caller iterating $item->add_ons straight after
    // create() must get a list, not null.
    $tenant = Tenant::factory()->create();

    $item = $this->runInTenant($tenant, function () use ($tenant): CatalogItem {
        $category = CatalogCategory::factory()->create(['tenant_id' => $tenant->id]);

        return CatalogItem::query()->create([
            'tenant_id' => $tenant->id,
            'catalog_category_id' => $category->id,
            'name' => 'Gel manicure',
        ]);
    });

    expect($item->add_ons)->toBeArray()->toBeEmpty()
        ->and($item->tags)->toBeArray()->toBeEmpty()
        ->and($item->price_is_from)->toBeFalse()
        ->and($item->is_featured)->toBeFalse()
        ->and($item->sort)->toBe(0);
});

test('add-ons, tags, price and duration round-trip through their casts', function (): void {
    $tenant = Tenant::factory()->create();
    $item = $this->runInTenant($tenant, fn (): CatalogItem => CatalogItem::factory()->fromPrice()->withAddOns()->featured()->create([
        'tenant_id' => $tenant->id,
        'tags' => ['popular', 'vegan polish'],
    ]));
    $item = CatalogItem::query()->findOrFail($item->getKey());

    expect($item->add_ons)->toBe([
        ['name' => 'French tip', 'price_cents' => 1000],
        ['name' => 'Chrome powder', 'price_cents' => 1500],
    ])
        ->and($item->tags)->toBe(['popular', 'vegan polish'])
        ->and($item->price_cents)->toBe(6500)
        ->and($item->price_is_from)->toBeTrue()
        ->and($item->duration_minutes)->toBe(90)
        ->and($item->is_featured)->toBeTrue();
});

test('the price reads as a visitor would see it', function (?int $priceCents, bool $isFrom, string $currency, ?string $expected): void {
    $item = CatalogItem::factory()->make([
        'tenant_id' => 'x',
        'catalog_category_id' => 1,
        'price_cents' => $priceCents,
        'price_is_from' => $isFrom,
    ]);

    expect($item->formattedPrice($currency))->toBe($expected);
})->with([
    'whole dollars' => [4500, false, 'USD', '$45'],
    'a from price' => [6500, true, 'USD', 'from $65'],
    'cents shown only when there are some' => [3550, false, 'EUR', '€35.50'],
    'thousands grouped' => [120000, false, 'GBP', '£1,200'],
    'a lower-case code' => [2500, false, 'cad', '$25'],
    'a currency with no bare symbol keeps its code' => [3500, false, 'CHF', 'CHF 35'],
    'ask for price' => [null, false, 'USD', null],
    'ask for price ignores the from flag' => [null, true, 'USD', null],
]);

test('the duration reads as a visitor would see it', function (?int $minutes, ?string $expected): void {
    $item = CatalogItem::factory()->make([
        'tenant_id' => 'x',
        'catalog_category_id' => 1,
        'duration_minutes' => $minutes,
    ]);

    expect($item->formattedDuration())->toBe($expected);
})->with([
    'under an hour' => [45, '45 min'],
    'exactly an hour' => [60, '1 hr'],
    'hours and minutes' => [75, '1 hr 15 min'],
    'whole hours' => [120, '2 hr'],
    'no duration' => [null, null],
]);

test('the ordered scope sorts by sort, then by id', function (): void {
    $tenant = Tenant::factory()->create();
    $expected = $this->runInTenant($tenant, function () use ($tenant): array {
        $category = CatalogCategory::factory()->create(['tenant_id' => $tenant->id]);
        $attributes = ['tenant_id' => $tenant->id, 'catalog_category_id' => $category->id];

        $last = CatalogItem::factory()->create([...$attributes, 'sort' => 3]);
        $tiedFirst = CatalogItem::factory()->create([...$attributes, 'sort' => 0]);
        $tiedSecond = CatalogItem::factory()->create([...$attributes, 'sort' => 0]);

        return [$tiedFirst->id, $tiedSecond->id, $last->id];
    });

    expect(CatalogItem::query()->ordered()->pluck('id')->all())->toBe($expected);
});

test('to array', function (): void {
    $tenant = Tenant::factory()->create();
    $item = $this->runInTenant($tenant, fn (): CatalogItem => CatalogItem::factory()->create(['tenant_id' => $tenant->id]));
    $item = CatalogItem::query()->findOrFail($item->getKey());

    expect(array_keys($item->toArray()))
        ->toBe([
            'id',
            'tenant_id',
            'catalog_category_id',
            'name',
            'description',
            'price_cents',
            'price_is_from',
            'duration_minutes',
            'add_ons',
            'tags',
            'media_id',
            'is_featured',
            'sort',
            'created_at',
            'updated_at',
        ]);
});
