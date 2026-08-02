<?php

declare(strict_types=1);

use App\Actions\Library\ExtractPhotoPalette;
use App\StockPhotos\PhotoPalette;

it('reads the dominant colour off a single-colour photo', function (): void {
    $palette = new ExtractPhotoPalette()->handle($this->bandedPng([[192, 128, 64]]));

    expect($palette)->toBeInstanceOf(PhotoPalette::class)
        ->and($palette?->dominant)->toBe('#c08040')
        ->and($palette?->swatches)->toBe(['#c08040'])
        ->and($palette?->isDark)->toBeFalse();
});

it('orders swatches by how much of the photo they cover', function (): void {
    // Three bands of white to one of black: white dominates, black still
    // registers as a swatch.
    $palette = new ExtractPhotoPalette()->handle($this->bandedPng([
        [255, 255, 255],
        [255, 255, 255],
        [255, 255, 255],
        [0, 0, 0],
    ]));

    expect($palette?->swatches)->toBe(['#ffffff', '#000000'])
        ->and($palette?->dominant)->toBe('#ffffff');
});

it('calls a photo dark when its overall brightness could carry white text', function (): void {
    $dark = new ExtractPhotoPalette()->handle($this->bandedPng([[20, 20, 28], [40, 40, 52]]));
    $light = new ExtractPhotoPalette()->handle($this->bandedPng([[240, 238, 230], [200, 198, 190]]));

    expect($dark?->isDark)->toBeTrue()
        ->and($light?->isDark)->toBeFalse();
});

it('measures darkness over the whole photo, not just the dominant colour', function (): void {
    // Mid-grey covers most of the frame and on its own sits just ABOVE the
    // white/black crossover, so a dominant-swatch check would call this photo
    // light. The near-black remainder pulls the mean below it — which is the
    // honest answer for text laid over the whole thing.
    $palette = new ExtractPhotoPalette()->handle($this->bandedPng([
        [128, 128, 128],
        [128, 128, 128],
        [128, 128, 128],
        [128, 128, 128],
        [0, 0, 0],
        [0, 0, 0],
        [0, 0, 0],
    ]));

    expect($palette?->dominant)->toBe('#808080')
        ->and($palette?->isDark)->toBeTrue();
});

it('gives up quietly on bytes that are not an image', function (): void {
    // A photo whose palette cannot be read is still a good photo — the import
    // keeps it with null colour metadata rather than failing.
    expect(new ExtractPhotoPalette()->handle('not-an-image'))->toBeNull();
});

it('gives up quietly on a fully transparent image, which has no colour to sample', function (): void {
    $image = imagecreatetruecolor(8, 8);
    imagesavealpha($image, true);
    imagealphablending($image, false);
    imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));

    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();

    expect(new ExtractPhotoPalette()->handle($bytes))->toBeNull();
});
