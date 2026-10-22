<?php

declare(strict_types=1);

use App\Models\CatalogCategory;
use App\Models\CatalogItem;
use App\Models\GalleryImage;
use App\Models\Tenant;
use Illuminate\Database\QueryException;

test('tenant relation returns the owning tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $category = $this->runInTenant($tenant, fn (): CatalogCategory => CatalogCategory::factory()->create(['tenant_id' => $tenant->id]));
    $category = CatalogCategory::query()->findOrFail($category->getKey());

    expect($category->tenant->is($tenant))->toBeTrue();
});

test('items relation returns the category items in operator order', function (): void {
    $tenant = Tenant::factory()->create();
    [$categoryId, $expected] = $this->runInTenant($tenant, function () use ($tenant): array {
        $category = CatalogCategory::factory()->create(['tenant_id' => $tenant->id]);
        $second = CatalogItem::factory()->create(['tenant_id' => $tenant->id, 'catalog_category_id' => $category->id, 'sort' => 2]);
        $first = CatalogItem::factory()->create(['tenant_id' => $tenant->id, 'catalog_category_id' => $category->id, 'sort' => 1]);
        // Another category's item must not leak in.
        CatalogItem::factory()->create(['tenant_id' => $tenant->id]);

        return [$category->id, [$first->id, $second->id]];
    });

    expect(CatalogCategory::query()->findOrFail($categoryId)->items->pluck('id')->all())->toBe($expected);
});

test('gallery images relation returns the work tagged with the category', function (): void {
    $tenant = Tenant::factory()->create();
    [$categoryId, $imageId] = $this->runInTenant($tenant, function () use ($tenant): array {
        $category = CatalogCategory::factory()->create(['tenant_id' => $tenant->id]);
        $image = GalleryImage::factory()->create(['tenant_id' => $tenant->id, 'catalog_category_id' => $category->id]);
        GalleryImage::factory()->create(['tenant_id' => $tenant->id]);

        return [$category->id, $image->id];
    });

    expect(CatalogCategory::query()->findOrFail($categoryId)->galleryImages->pluck('id')->all())->toBe([$imageId]);
});

test('the ordered scope sorts by sort, then by id', function (): void {
    $tenant = Tenant::factory()->create();
    $expected = $this->runInTenant($tenant, function () use ($tenant): array {
        $last = CatalogCategory::factory()->create(['tenant_id' => $tenant->id, 'sort' => 5]);
        $tiedFirst = CatalogCategory::factory()->create(['tenant_id' => $tenant->id, 'sort' => 1]);
        $tiedSecond = CatalogCategory::factory()->create(['tenant_id' => $tenant->id, 'sort' => 1]);

        return [$tiedFirst->id, $tiedSecond->id, $last->id];
    });

    expect(CatalogCategory::query()->ordered()->pluck('id')->all())->toBe($expected);
});

test('deleting a category deletes its items but keeps its gallery photos', function (): void {
    // Items cannot exist outside a category; a photo is still the salon's work.
    $tenant = Tenant::factory()->create();
    $imageId = $this->runInTenant($tenant, function () use ($tenant): int {
        $category = CatalogCategory::factory()->create(['tenant_id' => $tenant->id]);
        CatalogItem::factory()->create(['tenant_id' => $tenant->id, 'catalog_category_id' => $category->id]);
        $image = GalleryImage::factory()->create(['tenant_id' => $tenant->id, 'catalog_category_id' => $category->id]);

        $category->delete();

        return $image->id;
    });

    expect(CatalogItem::query()->count())->toBe(0)
        ->and(GalleryImage::query()->findOrFail($imageId)->catalog_category_id)->toBeNull();
});

test('a tenant cannot have two categories with the same name', function (): void {
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function () use ($tenant): void {
        CatalogCategory::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Manicures']);

        expect(fn (): CatalogCategory => CatalogCategory::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Manicures']))
            ->toThrow(QueryException::class);
    });
});

test('two tenants can both have a category with the same name', function (): void {
    foreach ([Tenant::factory()->create(), Tenant::factory()->create()] as $tenant) {
        $this->runInTenant($tenant, fn (): CatalogCategory => CatalogCategory::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Manicures',
        ]));
    }

    expect(CatalogCategory::query()->where('name', 'Manicures')->count())->toBe(2);
});

test('to array', function (): void {
    $tenant = Tenant::factory()->create();
    $category = $this->runInTenant($tenant, fn (): CatalogCategory => CatalogCategory::factory()->create(['tenant_id' => $tenant->id]));
    $category = CatalogCategory::query()->findOrFail($category->getKey());

    expect(array_keys($category->toArray()))
        ->toBe([
            'id',
            'tenant_id',
            'name',
            'description',
            'sort',
            'created_at',
            'updated_at',
        ]);
});
