<?php

declare(strict_types=1);

use App\StockPhotos\PhotoOrientation;

test('dimensions resolve to the shape a layout would call them', function (int $width, int $height, PhotoOrientation $expected): void {
    expect(PhotoOrientation::fromDimensions($width, $height))->toBe($expected);
})->with([
    'wide photograph' => [4000, 2667, PhotoOrientation::Landscape],
    'tall photograph' => [2667, 4000, PhotoOrientation::Portrait],
    'exactly square' => [1200, 1200, PhotoOrientation::Square],
    // Inside the 5% tolerance: square enough that calling it landscape would
    // make it a false positive for every "I need a wide hero" search.
    'near-square' => [1600, 1550, PhotoOrientation::Square],
    'just past the tolerance' => [1600, 1400, PhotoOrientation::Landscape],
    // A provider that reported no dimensions must not divide by zero.
    'no dimensions' => [0, 0, PhotoOrientation::Square],
]);
