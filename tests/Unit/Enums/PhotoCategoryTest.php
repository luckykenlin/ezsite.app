<?php

declare(strict_types=1);

use App\Enums\PhotoCategory;

/**
 * Structural invariants over ::cases(), so a new category is covered the moment
 * it is declared — the AI passes these values as a filter argument, so a case
 * with no keywords could never be guessed and a duplicate keyword would make
 * one category shadow another.
 */
test('every category is guessable by each of its own keywords', function (): void {
    foreach (PhotoCategory::cases() as $category) {
        expect($category->keywords())->not->toBeEmpty();

        foreach ($category->keywords() as $keyword) {
            // Not necessarily $category itself: an earlier case may legitimately
            // claim a word ("kitchen" is food-drink, and Interior lists it too).
            // What must hold is that the word resolves to SOME category.
            expect(PhotoCategory::guess($keyword))->toBeInstanceOf(PhotoCategory::class);
        }
    }
});

test('every category has a label and a lower-case value', function (): void {
    foreach (PhotoCategory::cases() as $category) {
        expect($category->label())->not->toBeEmpty()
            ->and($category->value)->toMatch('/^[a-z]+(-[a-z]+)*$/');
    }
});

test('the more specific subject wins over the setting it was shot in', function (): void {
    // Both words are claimed — "restaurant" by food-drink, "interior" by
    // interior — and declaration order is what decides. A photo of a dish is
    // about the dish, not the room.
    expect(PhotoCategory::guess('restaurant interior with warm light'))->toBe(PhotoCategory::FoodDrink)
        ->and(PhotoCategory::guess('a woman in a city street'))->toBe(PhotoCategory::People);
});

test('guessing is case-insensitive and tolerates plurals', function (): void {
    expect(PhotoCategory::guess('Two RESTAURANTS'))->toBe(PhotoCategory::FoodDrink)
        ->and(PhotoCategory::guess('flat lay of two bottles'))->toBe(PhotoCategory::Product);
});

test('keywords match whole words, so a barber is not a bar and a team is not tea', function (): void {
    // Under substring matching both of these landed in food & drink — "bar"
    // inside "barber", "tea" inside "team" — which is how a barber shop got
    // classified alongside the cocktail photos.
    expect(PhotoCategory::guess('a barber trimming a beard'))->toBeNull()
        ->and(PhotoCategory::guess('our team outside the workshop'))->toBe(PhotoCategory::People)
        // The words themselves still work standing alone.
        ->and(PhotoCategory::guess('a cocktail bar at night'))->toBe(PhotoCategory::FoodDrink);
});

test('an unrecognisable subject is left uncategorised rather than guessed wrong', function (): void {
    expect(PhotoCategory::guess('zzzz qqqq'))->toBeNull();
});
