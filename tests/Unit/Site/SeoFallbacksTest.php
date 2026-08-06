<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Media;
use App\Models\Tenant;
use App\Site\SeoFallbacks;
use Illuminate\Support\Facades\Storage;

// The fallbacks only READ the business, so `make()` keeps these tests free of
// tenant-context ceremony — and of the one-business-per-tenant unique index.

it('suffixes the title with the business name, and leaves it bare without one', function (): void {
    $business = Business::factory()->make(['name' => 'Fern & Petal']);

    $fallbacks = resolve(SeoFallbacks::class);

    expect($fallbacks->title('About', $business))->toBe('About - Fern & Petal')
        ->and($fallbacks->title('About', null))->toBe('About');
});

it('prefers the surface line, then the tagline, then the stock description', function (): void {
    $tagged = Business::factory()->make(['tagline' => 'Flowers for every day', 'description' => 'A florist in the old town.']);
    $stock = Business::factory()->make(['tagline' => null, 'description' => 'Stock copy.']);

    $fallbacks = resolve(SeoFallbacks::class);

    expect($fallbacks->description('Our own line', $tagged))->toBe('Our own line')
        ->and($fallbacks->description(null, $tagged))->toBe('Flowers for every day')
        ->and($fallbacks->description(null, $stock))->toBe('Stock copy.')
        ->and($fallbacks->description(null, null))->toBeNull();
});

it('clamps the description to a share-card length', function (): void {
    $clamped = resolve(SeoFallbacks::class)->description(str_repeat('word ', 60), null);

    expect($clamped)->toEndWith('...')
        // Str::limit(160) keeps 160 characters plus its ellipsis.
        ->and(mb_strlen((string) $clamped))->toBeLessThanOrEqual(163);
});

it('walks the media picks in order and falls back to the logo', function (): void {
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function () use ($tenant): void {
        Storage::fake('public');

        $business = Business::factory()->make(['tenant_id' => $tenant->id, 'logo_path' => null]);
        $cover = Media::factory()->create(['tenant_id' => $tenant->id]);

        $fallbacks = resolve(SeoFallbacks::class);

        // A null first pick (say, a share card not rendered yet) must not block
        // the second — that ordering IS the feature.
        expect($fallbacks->image($business, null, $cover->id))->toContain($cover->path)
            ->and($fallbacks->image(null))->toBeNull();
    });
});
