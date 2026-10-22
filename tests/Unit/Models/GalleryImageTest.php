<?php

declare(strict_types=1);

use App\Models\CatalogCategory;
use App\Models\GalleryImage;
use App\Models\Media;
use App\Models\Tenant;

test('tenant relation returns the owning tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $image = $this->runInTenant($tenant, fn (): GalleryImage => GalleryImage::factory()->create(['tenant_id' => $tenant->id]));
    $image = GalleryImage::query()->findOrFail($image->getKey());

    expect($image->tenant->is($tenant))->toBeTrue();
});

test('media relation returns the photo', function (): void {
    $tenant = Tenant::factory()->create();
    [$imageId, $mediaId] = $this->runInTenant($tenant, function () use ($tenant): array {
        $media = Media::factory()->create(['tenant_id' => $tenant->id]);
        $image = GalleryImage::factory()->create(['tenant_id' => $tenant->id, 'media_id' => $media->id]);

        return [$image->id, $media->id];
    });

    expect(GalleryImage::query()->findOrFail($imageId)->media->getKey())->toBe($mediaId);
});

test('category relation returns the service category the work is tagged with', function (): void {
    $tenant = Tenant::factory()->create();
    [$imageId, $categoryId] = $this->runInTenant($tenant, function () use ($tenant): array {
        $category = CatalogCategory::factory()->create(['tenant_id' => $tenant->id]);
        $image = GalleryImage::factory()->create(['tenant_id' => $tenant->id, 'catalog_category_id' => $category->id]);

        return [$image->id, $category->id];
    });

    expect(GalleryImage::query()->findOrFail($imageId)->category->getKey())->toBe($categoryId);
});

test('deleting the photo deletes the gallery entry', function (): void {
    // The image IS the row: an entry with no photo has nothing to show.
    $tenant = Tenant::factory()->create();
    $this->runInTenant($tenant, function () use ($tenant): void {
        $media = Media::factory()->create(['tenant_id' => $tenant->id]);
        GalleryImage::factory()->create(['tenant_id' => $tenant->id, 'media_id' => $media->id]);

        $media->delete();
    });

    expect(GalleryImage::query()->count())->toBe(0);
});

test('the ordered scope sorts by sort, then by id', function (): void {
    $tenant = Tenant::factory()->create();
    $expected = $this->runInTenant($tenant, function () use ($tenant): array {
        $last = GalleryImage::factory()->create(['tenant_id' => $tenant->id, 'sort' => 9]);
        $tiedFirst = GalleryImage::factory()->create(['tenant_id' => $tenant->id, 'sort' => 0]);
        $tiedSecond = GalleryImage::factory()->create(['tenant_id' => $tenant->id, 'sort' => 0]);

        return [$tiedFirst->id, $tiedSecond->id, $last->id];
    });

    expect(GalleryImage::query()->ordered()->pluck('id')->all())->toBe($expected);
});

test('to array', function (): void {
    $tenant = Tenant::factory()->create();
    $image = $this->runInTenant($tenant, fn (): GalleryImage => GalleryImage::factory()->create(['tenant_id' => $tenant->id]));
    $image = GalleryImage::query()->findOrFail($image->getKey());

    expect(array_keys($image->toArray()))
        ->toBe([
            'id',
            'tenant_id',
            'media_id',
            'caption',
            'catalog_category_id',
            'sort',
            'created_at',
            'updated_at',
        ]);
});
