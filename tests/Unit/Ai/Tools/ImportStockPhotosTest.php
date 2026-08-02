<?php

declare(strict_types=1);

use App\Actions\Library\AdoptLibraryPhoto;
use App\Actions\Library\DerivePhotoKeywords;
use App\Actions\Library\ExtractPhotoPalette;
use App\Actions\Library\FindOrImportLibraryPhoto;
use App\Actions\OptimizeImage;
use App\Ai\PhotoAnnouncement;
use App\Ai\Tools\ImportStockPhotos;
use App\Models\LibraryPhoto;
use App\Models\Media;
use App\Models\Tenant;
use App\StockPhotos\NullProvider;
use App\StockPhotos\PhotoOrientation;
use App\StockPhotos\StockPhoto;
use App\StockPhotos\StockPhotoProvider;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

function importStockPhotosTool(StockPhotoProvider $provider): ImportStockPhotos
{
    return new ImportStockPhotos(
        $provider,
        new FindOrImportLibraryPhoto($provider, new ExtractPhotoPalette, new DerivePhotoKeywords, new OptimizeImage),
        new PhotoAnnouncement(resolve(AdoptLibraryPhoto::class)),
    );
}

it('imports into the shared library and announces the tenant media ids', function (): void {
    Http::fake(['images.pexels.com/*' => Http::response($this->bandedPng([[192, 128, 64]]))]);
    $provider = $this->poolStockPhotoProvider();

    $result = $this->runInTenant(
        Tenant::factory()->create(),
        fn (): string => importStockPhotosTool($provider)->handle(new Request(['query' => 'cafe interior', 'count' => 2])),
    );

    $media = Media::query()->get();

    expect($media)->toHaveCount(2)
        ->and(mb_substr_count($result, 'media id '))->toBe(2)
        // Imports join the CATALOGUE, so the next site gets them free.
        ->and(LibraryPhoto::query()->count())->toBe(2)
        ->and(LibraryPhoto::query()->firstOrFail()->search_query)->toBe('cafe interior')
        ->and($media->first()?->getAttribute('library_photo_id'))->not->toBeNull();
});

it('passes the requested orientation through to the provider', function (): void {
    Http::fake(['images.pexels.com/*' => Http::response($this->bandedPng([[10, 10, 10]]))]);
    $provider = $this->poolStockPhotoProvider();

    $this->runInTenant(
        Tenant::factory()->create(),
        fn (): string => importStockPhotosTool($provider)->handle(new Request(['query' => 'tall doorway', 'orientation' => 'portrait'])),
    );

    expect($provider->searches)->toHaveCount(1)
        ->and($provider->searches[0]['count'])->toBe(1);
});

it('caps how many photos one call imports', function (): void {
    Http::fake(['images.pexels.com/*' => Http::response($this->bandedPng([[10, 10, 10]]))]);
    $provider = $this->poolStockPhotoProvider(photos: 20);

    $this->runInTenant(
        Tenant::factory()->create(),
        fn (): string => importStockPhotosTool($provider)->handle(new Request(['query' => 'shelving', 'count' => 50])),
    );

    expect(Media::query()->count())->toBe(4);
});

it('imports only what it asked for, even from a provider that ignores the count', function (): void {
    // PexelsProvider passes $count as `per_page`, so this should never happen —
    // but if a provider ever over-returns, the cost is real downloads and real
    // rate-limit tokens, so the tool stops at what it wanted.
    Http::fake(['images.pexels.com/*' => Http::response($this->bandedPng([[10, 10, 10]]))]);

    $overEager = new class implements StockPhotoProvider
    {
        public function search(string $query, PhotoOrientation $orientation, int $count): array
        {
            return array_map(
                fn (int $index): StockPhoto => new StockPhoto('pexels', (string) $index, 'https://images.pexels.com/photos/'.$index.'.jpeg', 4000, 2667, 'Photo '.$index),
                range(1, 10),
            );
        }

        public function trackDownload(StockPhoto $photo): void
        {
            //
        }
    };

    $this->runInTenant(
        Tenant::factory()->create(),
        fn (): string => importStockPhotosTool($overEager)->handle(new Request(['query' => 'cafe', 'count' => 2])),
    );

    expect(LibraryPhoto::query()->count())->toBe(2)
        ->and(Media::query()->count())->toBe(2);

    Http::assertSentCount(2);
});

it('reports that it found nothing when no provider key is configured', function (): void {
    // The NullProvider path: the tool is inert rather than absent, so the model
    // gets an answer instead of hallucinating a verb it does not have.
    $result = $this->runInTenant(
        Tenant::factory()->create(),
        fn (): string => importStockPhotosTool(new NullProvider)->handle(new Request(['query' => 'anything'])),
    );

    expect($result)->toContain('No photos could be imported for "anything"')
        ->and(LibraryPhoto::query()->count())->toBe(0);
});

it('reports a failed download rather than announcing a photo that did not land', function (): void {
    Http::fake(['images.pexels.com/*' => Http::response('gone', 404)]);
    $provider = $this->poolStockPhotoProvider();

    $result = $this->runInTenant(
        Tenant::factory()->create(),
        fn (): string => importStockPhotosTool($provider)->handle(new Request(['query' => 'cafe'])),
    );

    expect($result)->toContain('No photos could be imported')
        ->and(Media::query()->count())->toBe(0);
});

it('asks for search words when given none', function (): void {
    $result = importStockPhotosTool(new NullProvider)->handle(new Request([]));

    expect($result)->toContain('No search words were given');
});

it('warns the model off passing a stock photo as the real business', function (): void {
    // The clause that stops a stock face becoming "our team" — the same rule
    // PopulateDraftImages enforces structurally by never filling those slots.
    expect(importStockPhotosTool(new NullProvider)->description())
        ->toContain('search the library first')
        ->toContain('real staff');
});

it('offers the model only orientations that exist', function (): void {
    $schema = importStockPhotosTool(new NullProvider)->schema(new JsonSchemaTypeFactory);

    expect($schema['orientation']->toArray()['enum'])->toBe(['landscape', 'portrait', 'square']);
});
