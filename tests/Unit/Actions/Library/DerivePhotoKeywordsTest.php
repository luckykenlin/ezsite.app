<?php

declare(strict_types=1);

use App\Actions\Library\DerivePhotoKeywords;
use App\Enums\PhotoCategory;

it('tags a photo with the meaningful words from the query and the alt text', function (): void {
    $derived = new DerivePhotoKeywords()->handle('coffee shop interior', 'A barista with the espresso machine');

    expect($derived['tags'])->toBe(['coffee', 'shop', 'interior', 'barista', 'espresso', 'machine'])
        ->and($derived['category'])->toBe(PhotoCategory::FoodDrink);
});

it('drops stop words and words too short to search by', function (): void {
    $derived = new DerivePhotoKeywords()->handle(null, 'The view from the front of a shop');

    // "the", "from", "front", "view" carry no search signal; "of", "a" are too
    // short to be worth an index entry.
    expect($derived['tags'])->toBe(['shop']);
});

it('deduplicates a word the query and the alt text both use', function (): void {
    $derived = new DerivePhotoKeywords()->handle('bakery counter', 'bakery counter with bread');

    expect($derived['tags'])->toBe(['bakery', 'counter', 'bread']);
});

it('folds an optional description into the signal', function (): void {
    $derived = new DerivePhotoKeywords()->handle(null, null, 'Golden dog on a lawn');

    expect($derived['tags'])->toBe(['golden', 'dog', 'lawn'])
        ->and($derived['category'])->toBe(PhotoCategory::Animal);
});

it('leaves a photo it cannot read uncategorised and untagged', function (): void {
    $derived = new DerivePhotoKeywords()->handle(null, null);

    expect($derived['tags'])->toBeEmpty()
        ->and($derived['category'])->toBeNull();
});
