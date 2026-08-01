<?php

declare(strict_types=1);

use App\StockPhotos\PexelsProvider;
use App\StockPhotos\PhotoOrientation;
use App\StockPhotos\StockPhotoProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

function pexelsSearchFixture(): array
{
    return ['photos' => [
        [
            'id' => 123,
            'width' => 4000,
            'height' => 2667,
            'url' => 'https://www.pexels.com/photo/123/',
            'alt' => 'A barber trimming a beard',
            'photographer' => 'Jane Doe',
            'photographer_url' => 'https://www.pexels.com/@jane',
            'src' => ['large2x' => 'https://images.pexels.com/photos/123/photo.jpeg?auto=compress&fm=jpg'],
        ],
        // No usable rendition: dropped rather than imported as a broken URL.
        ['id' => 456, 'src' => []],
        // Not even an object: a drifting payload degrades, never fatals.
        'junk',
    ]];
}

it('searches pexels and maps results to provider-neutral photos', function (): void {
    Http::fake(['api.pexels.com/*' => Http::response(pexelsSearchFixture())]);

    $photos = new PexelsProvider('test-key')->search('barber shop interior', PhotoOrientation::Landscape, 2);

    expect($photos)->toHaveCount(1)
        ->and($photos[0]->provider)->toBe('pexels')
        ->and($photos[0]->sourceId)->toBe('123')
        ->and($photos[0]->downloadUrl)->toContain('images.pexels.com/photos/123')
        ->and($photos[0]->width)->toBe(4000)
        ->and($photos[0]->height)->toBe(2667)
        ->and($photos[0]->alt)->toBe('A barber trimming a beard')
        ->and($photos[0]->photographerName)->toBe('Jane Doe')
        ->and($photos[0]->photographerUrl)->toBe('https://www.pexels.com/@jane')
        ->and($photos[0]->sourceUrl)->toBe('https://www.pexels.com/photo/123/');

    Http::assertSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return $request->hasHeader('Authorization', 'test-key')
            && $query['query'] === 'barber shop interior'
            && $query['orientation'] === 'landscape'
            && $query['per_page'] === '2';
    });
});

it('answers an empty list on a provider error, with a log', function (): void {
    Log::spy();
    Http::fake(['api.pexels.com/*' => Http::response(['error' => 'nope'], 500)]);

    expect(new PexelsProvider('test-key')->search('anything', PhotoOrientation::Landscape, 3))->toBe([]);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $message === 'stock_photos.search_failed'
            && $context['status'] === 500)
        ->once();
});

it('answers an empty list when the connection itself fails', function (): void {
    Log::spy();
    Http::fake(['api.pexels.com/*' => fn () => throw new ConnectionException('the wire is down')]);

    expect(new PexelsProvider('test-key')->search('anything', PhotoOrientation::Landscape, 3))->toBe([]);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => $message === 'stock_photos.search_failed')
        ->once();
});

it('stops searching when the app-side hourly rate limit is exhausted', function (): void {
    Log::spy();
    config()->set('stock-photos.rate_limit_per_hour', 1);
    Http::fake(['api.pexels.com/*' => Http::response(pexelsSearchFixture())]);
    RateLimiter::clear('stock-photos:pexels');

    $provider = new PexelsProvider('test-key');

    expect($provider->search('first', PhotoOrientation::Landscape, 1))->toHaveCount(1)
        // The second search never reaches the provider — a burst degrades to
        // photo-less drafts instead of provider 429s.
        ->and($provider->search('second', PhotoOrientation::Landscape, 1))->toBe([]);

    Http::assertSentCount(1);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => $message === 'stock_photos.rate_limited')
        ->once();
});

it('reports no downloads to pexels — their API requires no ping', function (): void {
    Http::fake();

    new PexelsProvider('test-key')->trackDownload(
        new App\StockPhotos\StockPhoto('pexels', '1', 'https://images.pexels.com/photos/1/photo.jpeg', 100, 100, 'x'),
    );

    // The interface hook exists for Unsplash-style compliance pings; the
    // Pexels driver deliberately makes none.
    Http::assertNothingSent();
});

it('is the bound provider when a pexels key is configured', function (): void {
    config()->set('services.pexels.key', 'test-key');

    expect(resolve(StockPhotoProvider::class))->toBeInstanceOf(PexelsProvider::class);
});
