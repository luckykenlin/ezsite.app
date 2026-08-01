<?php

declare(strict_types=1);

use App\Actions\Library\FindLibraryPhotos;
use App\Enums\PhotoCategory;
use App\Models\LibraryPhoto;
use App\StockPhotos\PhotoOrientation;

it('offers the least-used matching photo first', function (): void {
    // The ordering that keeps a shared library from making every site look the
    // same: demand spreads across the catalogue instead of piling onto whatever
    // matched first.
    $popular = LibraryPhoto::factory()->describing('cafe terrace')->create(['usage_count' => 12]);
    $fresh = LibraryPhoto::factory()->describing('cafe counter')->create(['usage_count' => 0]);
    $used = LibraryPhoto::factory()->describing('cafe window')->create(['usage_count' => 3]);

    $found = new FindLibraryPhotos()->handle('cafe', count: 3);

    expect(array_map(fn (LibraryPhoto $photo): int => $photo->id, $found))
        ->toBe([$fresh->id, $used->id, $popular->id]);
});

it('never offers a photo that was curated out', function (): void {
    $offered = LibraryPhoto::factory()->describing('bakery counter')->create();
    LibraryPhoto::factory()->describing('bakery counter')->unpublished()->create();

    $found = new FindLibraryPhotos()->handle('bakery', count: 5);

    expect(array_map(fn (LibraryPhoto $photo): int => $photo->id, $found))->toBe([$offered->id]);
});

it('filters orientation hard, because the wrong shape is a visibly broken page', function (): void {
    $landscape = LibraryPhoto::factory()->describing('a wide workshop')->create(['orientation' => PhotoOrientation::Landscape]);
    LibraryPhoto::factory()->describing('a tall workshop')->create(['orientation' => PhotoOrientation::Portrait]);

    $found = new FindLibraryPhotos()->handle('workshop', PhotoOrientation::Landscape, 5);

    expect(array_map(fn (LibraryPhoto $photo): int => $photo->id, $found))->toBe([$landscape->id]);
});

it('narrows by category and by darkness', function (): void {
    $wanted = LibraryPhoto::factory()->describing('a plated dish')->dark()->create(['category' => PhotoCategory::FoodDrink]);
    LibraryPhoto::factory()->describing('a plated dish')->create(['category' => PhotoCategory::FoodDrink]);
    LibraryPhoto::factory()->describing('a plated dish')->dark()->create(['category' => PhotoCategory::Product]);

    $found = new FindLibraryPhotos()->handle('dish', null, 5, PhotoCategory::FoodDrink, true);

    expect(array_map(fn (LibraryPhoto $photo): int => $photo->id, $found))->toBe([$wanted->id]);
});

it('returns nothing when nothing describes the subject', function (): void {
    LibraryPhoto::factory()->describing('an empty warehouse')->create();

    expect(new FindLibraryPhotos()->handle('hairdresser', count: 5))->toBeEmpty();
});

it('returns nothing when asked for no photos at all', function (): void {
    LibraryPhoto::factory()->describing('an empty warehouse')->create();

    expect(new FindLibraryPhotos()->handle('warehouse', count: 0))->toBeEmpty();
});
