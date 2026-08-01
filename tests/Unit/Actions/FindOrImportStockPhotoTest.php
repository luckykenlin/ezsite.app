<?php

declare(strict_types=1);

use App\Actions\FindOrImportStockPhoto;
use App\Models\Media;
use App\Models\Tenant;
use App\StockPhotos\PhotoOrientation;
use App\StockPhotos\StockPhoto;
use App\StockPhotos\StockPhotoProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * A provider double that records download tracking — the compliance hook an
 * Unsplash driver will rely on, so the import must actually call it.
 */
function trackingStockProvider(): StockPhotoProvider
{
    return new class implements StockPhotoProvider
    {
        /** @var list<string> */
        public array $tracked = [];

        public function search(string $query, PhotoOrientation $orientation, int $count): array
        {
            return [];
        }

        public function trackDownload(StockPhoto $photo): void
        {
            $this->tracked[] = $photo->sourceId;
        }
    };
}

function importablePhoto(string $sourceId = '123'): StockPhoto
{
    return new StockPhoto(
        provider: 'pexels',
        sourceId: $sourceId,
        downloadUrl: 'https://images.pexels.com/photos/'.$sourceId.'/photo.jpeg?auto=compress&fm=jpg',
        width: 4000,
        height: 2667,
        alt: 'A barber trimming a beard',
        photographerName: 'Jane Doe',
        photographerUrl: 'https://www.pexels.com/@jane',
        sourceUrl: 'https://www.pexels.com/photo/'.$sourceId.'/',
    );
}

it('downloads a new photo onto the tenant disk and records its provenance', function (): void {
    Http::fake(['images.pexels.com/*' => Http::response('jpeg-bytes')]);
    $provider = trackingStockProvider();

    $tenant = Tenant::factory()->create();

    // The existence check runs INSIDE the tenant context, where the public
    // disk root is tenant-suffixed — outside it the path would resolve
    // against the central root and miss.
    [$mediaId, $fileExists] = $this->runInTenant($tenant, function () use ($provider): array {
        $media = new FindOrImportStockPhoto($provider)->handle(importablePhoto('456'));

        return [$media?->getKey(), $media !== null && Storage::disk('public')->exists($media->path)];
    });

    $media = Media::query()->findOrFail($mediaId);

    expect($fileExists)->toBeTrue()
        ->and($media->tenant_id)->toBe($tenant->id)
        ->and($media->disk)->toBe('public')
        ->and($media->directory)->toBe('stock')
        ->and($media->width)->toBe(4000)
        ->and($media->height)->toBe(2667)
        ->and($media->alt)->toBe('A barber trimming a beard')
        ->and($media->type)->toBe('image/jpeg')
        ->and($media->getAttribute('source_provider'))->toBe('pexels')
        ->and($media->getAttribute('source_id'))->toBe('456')
        ->and($media->getAttribute('photographer_name'))->toBe('Jane Doe')
        ->and($provider->tracked)->toBe(['456']);
});

it('reuses an already-imported photo with zero HTTP and zero tracking', function (): void {
    Http::fake();
    $provider = trackingStockProvider();

    $tenant = Tenant::factory()->create();
    $existing = $this->runInTenant($tenant, fn (): Media => Media::factory()->stock()->create([
        'tenant_id' => $tenant->id,
        'source_id' => '123',
    ]));

    $found = $this->runInTenant(
        $tenant,
        fn (): ?Media => new FindOrImportStockPhoto($provider)->handle(importablePhoto('123')),
    );

    expect($found?->getKey())->toBe($existing->getKey())
        ->and($provider->tracked)->toBe([]);

    Http::assertNothingSent();
});

it('answers null on a failed download, with a log', function (): void {
    Log::spy();
    Http::fake(['images.pexels.com/*' => Http::response('gone', 404)]);

    $tenant = Tenant::factory()->create();

    $media = $this->runInTenant(
        $tenant,
        fn (): ?Media => new FindOrImportStockPhoto(trackingStockProvider())->handle(importablePhoto()),
    );

    expect($media)->toBeNull()
        ->and(Media::query()->count())->toBe(0);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $message === 'stock_photos.download_failed'
            && $context['status'] === 404)
        ->once();
});

it('answers null when the download connection fails', function (): void {
    Log::spy();
    Http::fake(['images.pexels.com/*' => fn () => throw new ConnectionException('the wire is down')]);

    $tenant = Tenant::factory()->create();

    $media = $this->runInTenant(
        $tenant,
        fn (): ?Media => new FindOrImportStockPhoto(trackingStockProvider())->handle(importablePhoto()),
    );

    expect($media)->toBeNull();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => $message === 'stock_photos.download_failed')
        ->once();
});
