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

/*
 * The bug the relevance ranking exists for, and the reason least-used-first is
 * only the TIEBREAK: a photo that shares one weak word with the query used to
 * outrank one that shared three, purely by being newer. On the demo sites that
 * put massage tables in a pizzeria's gallery.
 */
it('ranks by how much of the query a photo actually matches, not by novelty', function (): void {
    $onTopic = LibraryPhoto::factory()->describing('wood fired pizza oven interior')->create(['usage_count' => 9]);
    $oneWeakWord = LibraryPhoto::factory()->describing('massage room interior')->create(['usage_count' => 0]);

    $found = new FindLibraryPhotos()->handle('wood fired pizza interior', count: 2);

    expect(array_map(fn (LibraryPhoto $photo): int => $photo->id, $found))
        ->toBe([$onTopic->id, $oneWeakWord->id]);
});

it('still spreads demand between photos that match the query equally well', function (): void {
    // Relevance decides first, but among equals the least-used still wins —
    // otherwise a shared library makes every site look the same.
    $popular = LibraryPhoto::factory()->describing('bakery counter morning')->create(['usage_count' => 20]);
    $fresh = LibraryPhoto::factory()->describing('bakery counter morning')->create(['usage_count' => 0]);

    $found = new FindLibraryPhotos()->handle('bakery counter morning', count: 2);

    expect(array_map(fn (LibraryPhoto $photo): int => $photo->id, $found))
        ->toBe([$fresh->id, $popular->id]);
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
