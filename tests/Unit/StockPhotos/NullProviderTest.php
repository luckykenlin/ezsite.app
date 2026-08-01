<?php

declare(strict_types=1);

use App\StockPhotos\NullProvider;
use App\StockPhotos\PhotoOrientation;
use App\StockPhotos\StockPhoto;
use App\StockPhotos\StockPhotoProvider;

it('finds nothing and tracks nothing, keeping keyless environments inert', function (): void {
    $provider = new NullProvider;
    $photo = new StockPhoto('pexels', '1', 'https://example.com/x.jpg', 100, 100, 'x');

    $provider->trackDownload($photo);

    expect($provider->search('anything', PhotoOrientation::Landscape, 5))->toBe([]);
});

it('is the bound provider when no pexels key is configured', function (): void {
    config()->set('services.pexels.key', null);

    expect(resolve(StockPhotoProvider::class))->toBeInstanceOf(NullProvider::class);
});
