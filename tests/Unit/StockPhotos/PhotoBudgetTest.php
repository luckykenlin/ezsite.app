<?php

declare(strict_types=1);

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

it('builds itself from the per-site config budgets', function (): void {
    config()->set('stock-photos.max_searches_per_site', 0);

    expect(PhotoBudget::fromConfig()->canSearch())->toBeFalse();
});
