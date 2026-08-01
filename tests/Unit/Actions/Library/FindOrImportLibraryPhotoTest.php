<?php

declare(strict_types=1);

use App\Actions\Library\DerivePhotoKeywords;
use App\Actions\Library\ExtractPhotoPalette;
use App\Actions\Library\FindOrImportLibraryPhoto;
use App\Enums\PhotoCategory;
use App\Models\LibraryPhoto;
use App\Models\Tenant;
use App\StockPhotos\PhotoOrientation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

function importLibraryPhoto(): FindOrImportLibraryPhoto
{
    return new FindOrImportLibraryPhoto(
        test()->trackingStockPhotoProvider(),
        new ExtractPhotoPalette,
        new DerivePhotoKeywords,
    );
}

it('downloads a new photo onto the shared disk with its metadata and provenance', function (): void {
    Http::fake(['images.pexels.com/*' => Http::response($this->bandedPng([[192, 128, 64]]))]);

    $photo = importLibraryPhoto()->handle($this->stockPhoto('456'), 'cafe interior');

    expect($photo)->toBeInstanceOf(LibraryPhoto::class)
        ->and($photo?->disk)->toBe('library')
        ->and(Storage::disk('library')->exists((string) $photo?->path))->toBeTrue()
        ->and($photo?->source_id)->toBe('456')
        ->and($photo?->photographer_name)->toBe('Jane Doe')
        ->and($photo?->width)->toBe(4000)
        ->and($photo?->orientation)->toBe(PhotoOrientation::Landscape)
        ->and($photo?->category)->toBe(PhotoCategory::FoodDrink)
        ->and($photo?->tags)->toContain('espresso', 'cafe')
        ->and($photo?->search_query)->toBe('cafe interior')
        ->and($photo?->dominant_color)->toBe('#c08040')
        ->and($photo?->is_dark)->toBeFalse()
        // Published on arrival — an import waiting for review would make the
        // draft pipeline ship photo-less sites.
        ->and($photo?->published_at)->not->toBeNull();
});

it('keeps a photo whose palette could not be read rather than failing the import', function (): void {
    Http::fake(['images.pexels.com/*' => Http::response('not-an-image')]);

    $photo = importLibraryPhoto()->handle($this->stockPhoto());

    expect($photo)->toBeInstanceOf(LibraryPhoto::class)
        ->and($photo?->palette)->toBeNull()
        ->and($photo?->dominant_color)->toBeNull()
        ->and($photo?->is_dark)->toBeFalse();
});

it('reuses an existing catalogue row with zero HTTP and zero tracking', function (): void {
    Http::fake();
    $existing = LibraryPhoto::factory()->create(['provider' => 'pexels', 'source_id' => '123']);

    $found = importLibraryPhoto()->handle($this->stockPhoto('123'));

    expect($found?->getKey())->toBe($existing->getKey())
        ->and(LibraryPhoto::query()->count())->toBe(1);

    Http::assertNothingSent();
});

it('spends one download for a photo two different tenants both want', function (): void {
    // The whole point of a shared catalogue: under the old per-tenant dedup this
    // was two downloads, two rate-limit tokens and two files.
    Http::fake(['images.pexels.com/*' => Http::response($this->bandedPng([[10, 10, 10]]))]);

    $first = Tenant::factory()->create();
    $second = Tenant::factory()->create();

    $photos = [
        $this->runInTenant($first, fn (): ?LibraryPhoto => importLibraryPhoto()->handle($this->stockPhoto('789'))),
        $this->runInTenant($second, fn (): ?LibraryPhoto => importLibraryPhoto()->handle($this->stockPhoto('789'))),
    ];

    expect($photos[1]?->getKey())->toBe($photos[0]?->getKey())
        ->and(LibraryPhoto::query()->count())->toBe(1);

    Http::assertSentCount(1);
});

it('tells the provider a photo was downloaded, for the compliance hook', function (): void {
    Http::fake(['images.pexels.com/*' => Http::response($this->bandedPng([[10, 10, 10]]))]);
    $provider = $this->trackingStockPhotoProvider();

    new FindOrImportLibraryPhoto($provider, new ExtractPhotoPalette, new DerivePhotoKeywords)
        ->handle($this->stockPhoto('321'));

    expect($provider->tracked)->toBe(['321']);
});

it('answers null on a failed download, with a log', function (): void {
    Log::spy();
    Http::fake(['images.pexels.com/*' => Http::response('gone', 404)]);

    expect(importLibraryPhoto()->handle($this->stockPhoto()))->toBeNull()
        ->and(LibraryPhoto::query()->count())->toBe(0);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $message === 'stock_photos.download_failed'
            && $context['status'] === 404)
        ->once();
});

it('answers null when the download connection fails', function (): void {
    Log::spy();
    Http::fake(['images.pexels.com/*' => fn () => throw new ConnectionException('the wire is down')]);

    expect(importLibraryPhoto()->handle($this->stockPhoto()))->toBeNull();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => $message === 'stock_photos.download_failed')
        ->once();
});
