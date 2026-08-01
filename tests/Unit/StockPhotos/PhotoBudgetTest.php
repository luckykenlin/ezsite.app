<?php

declare(strict_types=1);

use App\Models\LibraryPhoto;
use App\StockPhotos\PhotoBudget;
use App\StockPhotos\StockPhoto;

function stockPhoto(string $sourceId): StockPhoto
{
    return new StockPhoto('pexels', $sourceId, 'https://images.example.com/'.$sourceId.'.jpg', 4000, 2667, 'Photo '.$sourceId);
}

it('spends searches and photos down to zero', function (): void {
    $budget = new PhotoBudget(searches: 1, photos: 2);

    expect($budget->canSearch())->toBeTrue();

    $budget->spendSearch();

    // Searches gone: no more searching, even with photo budget left.
    expect($budget->canSearch())->toBeFalse()
        ->and($budget->canTake())->toBeTrue();

    $budget->take(stockPhoto('1'));
    $budget->take(stockPhoto('2'));

    expect($budget->canTake())->toBeFalse();
});

it('cannot search once the photo budget is spent, whatever searches remain', function (): void {
    $budget = new PhotoBudget(searches: 5, photos: 1);

    $budget->take(stockPhoto('1'));

    // A search whose results could never be taken is a wasted API call.
    expect($budget->canSearch())->toBeFalse();
});

it('filters already-used photos out of new results', function (): void {
    $budget = new PhotoBudget(searches: 5, photos: 5);

    $budget->take(stockPhoto('1'));

    $fresh = $budget->unused([stockPhoto('1'), stockPhoto('2')]);

    expect($fresh)->toHaveCount(1)
        ->and($fresh[0]->sourceId)->toBe('2');
});

it('spends nothing on a photo reused from the shared library', function (): void {
    // This budget is provider SPEND. A photo another site already imported costs
    // no request and no download, so reuse must not eat into either counter —
    // otherwise a library big enough to fill a whole site would still be capped
    // as though every photo had been fetched.
    $budget = new PhotoBudget(searches: 1, photos: 1);

    $budget->reuse(LibraryPhoto::factory()->create());

    expect($budget->canSearch())->toBeTrue()
        ->and($budget->canTake())->toBeTrue();
});

it('shares one used-photo key space between the library and the provider', function (): void {
    // Without this, the same photograph could land on a page twice: once from the
    // library, once from a provider search that returned the same result.
    $budget = new PhotoBudget(searches: 5, photos: 5);
    $photo = LibraryPhoto::factory()->create(['provider' => 'pexels', 'source_id' => '1']);

    $budget->reuse($photo);

    expect($budget->unused([stockPhoto('1'), stockPhoto('2')]))->toHaveCount(1)
        ->and($budget->unusedLibrary([$photo]))->toBeEmpty();
});

it('keys a library photo with no provider source id by its own id', function (): void {
    // Nothing imports these today, but a null source id must not collide every
    // such photo into one key and hide them all after the first.
    $budget = new PhotoBudget(searches: 5, photos: 5);
    $first = LibraryPhoto::factory()->create(['source_id' => null]);
    $second = LibraryPhoto::factory()->create(['source_id' => null]);

    $budget->reuse($first);

    expect($budget->unusedLibrary([$first, $second]))->toHaveCount(1)
        ->and($budget->unusedLibrary([$first, $second])[0]->id)->toBe($second->id);
});

it('builds itself from the per-site config budgets', function (): void {
    config()->set('stock-photos.max_searches_per_site', 0);

    expect(PhotoBudget::fromConfig()->canSearch())->toBeFalse();
});
