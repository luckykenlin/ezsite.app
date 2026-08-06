<?php

declare(strict_types=1);

use App\Actions\FindOrImportStockPhoto;
use App\Models\LibraryPhoto;
use App\Models\Media;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * The façade over the two halves — catalogue import then per-tenant adoption.
 * The halves have their own tests; these cover the composition, which is what
 * the draft pipeline and the chat tools depend on.
 */
it('lands a provider photo in both the shared catalogue and the tenant library', function (): void {
    Http::fake(['images.pexels.com/*' => Http::response($this->bandedPng([[192, 128, 64]]))]);

    $tenant = Tenant::factory()->create();

    // The file check runs INSIDE the tenant context, where the public disk root
    // is tenant-suffixed — outside it the path would resolve against the central
    // root and miss.
    [$mediaId, $fileExists] = $this->runInTenant($tenant, function (): array {
        $media = resolve(FindOrImportStockPhoto::class)->handle($this->stockPhoto('456'), 'cafe interior');

        return [$media?->getKey(), $media !== null && Storage::disk('public')->exists($media->path)];
    });

    $media = Media::query()->findOrFail($mediaId);
    $photo = LibraryPhoto::query()->firstOrFail();

    expect($fileExists)->toBeTrue()
        ->and($media->tenant_id)->toBe($tenant->id)
        ->and($media->disk)->toBe('public')
        ->and($media->directory)->toBe('stock')
        ->and($media->getAttribute('library_photo_id'))->toBe($photo->id)
        ->and($media->getAttribute('source_id'))->toBe('456')
        ->and($photo->search_query)->toBe('cafe interior')
        // Adoption is what marks the photo as spoken for.
        ->and($photo->usage_count)->toBe(1);
});

it('gives a second tenant the same catalogue row and its own media row', function (): void {
    Http::fake(['images.pexels.com/*' => Http::response($this->bandedPng([[10, 10, 10]]))]);

    $first = Tenant::factory()->create();
    $second = Tenant::factory()->create();

    $media = [
        $this->runInTenant($first, fn (): ?Media => resolve(FindOrImportStockPhoto::class)->handle($this->stockPhoto('789'))),
        $this->runInTenant($second, fn (): ?Media => resolve(FindOrImportStockPhoto::class)->handle($this->stockPhoto('789'))),
    ];

    expect($media[0]?->getKey())->not->toBe($media[1]?->getKey())
        ->and(LibraryPhoto::query()->count())->toBe(1)
        ->and(LibraryPhoto::query()->firstOrFail()->usage_count)->toBe(2);

    // One download for two tenants: the saving this whole feature exists for.
    Http::assertSentCount(1);
});

it('answers null without adopting anything when the import fails', function (): void {
    Http::fake(['images.pexels.com/*' => Http::response('gone', 404)]);

    $tenant = Tenant::factory()->create();

    $media = $this->runInTenant(
        $tenant,
        fn (): ?Media => resolve(FindOrImportStockPhoto::class)->handle($this->stockPhoto()),
    );

    expect($media)->toBeNull()
        ->and(Media::query()->count())->toBe(0)
        ->and(LibraryPhoto::query()->count())->toBe(0);
});
