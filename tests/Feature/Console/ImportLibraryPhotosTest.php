<?php

declare(strict_types=1);

use App\Models\LibraryPhoto;
use App\Models\Media;
use App\StockPhotos\NullProvider;
use App\StockPhotos\StockPhotoProvider;
use Illuminate\Support\Facades\Http;

it('seeds the shared catalogue from central context, adopting nothing', function (): void {
    // No tenant is involved: the photos become usable by every site, and a
    // command that wrote tenant-owned rows would be writing them unscoped.
    Http::fake(['images.pexels.com/*' => Http::response($this->bandedPng([[192, 128, 64]]))]);
    app()->instance(StockPhotoProvider::class, $this->poolStockPhotoProvider());

    $this->artisan('library:import', ['query' => 'coffee shop interior', '--count' => 3])
        ->expectsOutputToContain('3 imported, 0 already in the library, 0 failed.')
        ->assertSuccessful();

    expect(LibraryPhoto::query()->count())->toBe(3)
        ->and(Media::query()->count())->toBe(0)
        ->and(LibraryPhoto::query()->firstOrFail()->search_query)->toBe('coffee shop interior');
});

it('reports photos the catalogue already had rather than re-downloading them', function (): void {
    Http::fake(['images.pexels.com/*' => Http::response($this->bandedPng([[10, 10, 10]]))]);
    app()->instance(StockPhotoProvider::class, $this->poolStockPhotoProvider());

    $this->artisan('library:import', ['query' => 'shelving', '--count' => 2])->assertSuccessful();
    $this->artisan('library:import', ['query' => 'shelving', '--count' => 2])
        ->expectsOutputToContain('0 imported, 2 already in the library, 0 failed.')
        ->assertSuccessful();

    expect(LibraryPhoto::query()->count())->toBe(2);

    Http::assertSentCount(2);
});

it('counts a photo whose download failed as failed, not imported', function (): void {
    Http::fake(['images.pexels.com/*' => Http::response('gone', 404)]);
    app()->instance(StockPhotoProvider::class, $this->poolStockPhotoProvider());

    $this->artisan('library:import', ['query' => 'cafe', '--count' => 2])
        ->expectsOutputToContain('0 imported, 0 already in the library, 2 failed.')
        ->assertSuccessful();

    expect(LibraryPhoto::query()->count())->toBe(0);
});

it('explains an empty result instead of reporting a silent zero', function (): void {
    app()->instance(StockPhotoProvider::class, new NullProvider);

    $this->artisan('library:import', ['query' => 'anything'])
        ->expectsOutputToContain('STOCK_PHOTOS_ENABLED')
        ->assertSuccessful();
});

it('rejects a count below one', function (): void {
    $this->artisan('library:import', ['query' => 'cafe', '--count' => 0])
        ->expectsOutputToContain('--count must be at least 1.')
        ->assertFailed();
});

it('rejects an orientation the provider has no word for', function (): void {
    $this->artisan('library:import', ['query' => 'cafe', '--orientation' => 'diagonal'])
        ->expectsOutputToContain('landscape, portrait, square')
        ->assertFailed();
});
