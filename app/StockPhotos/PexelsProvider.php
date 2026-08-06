<?php

declare(strict_types=1);

namespace App\StockPhotos;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Pexels photo search (https://www.pexels.com/api/). Chosen as the first
 * driver over Unsplash deliberately: 200 requests/hour without an approval
 * process, attribution appreciated but not required, and — decisively — no
 * hotlinking requirement, so photos may be imported into tenant media (see
 * the gate documented on {@see StockPhotoProvider}).
 *
 * Every failure path answers an empty list with a log line, never an
 * exception: the caller is a queued job whose worst acceptable outcome is a
 * draft without photos. The app-side rate limiter sits just under the
 * provider's hourly ceiling so a burst of generations degrades the same way
 * instead of burning attempts on 429s.
 */
final readonly class PexelsProvider implements StockPhotoProvider
{
    private const string SEARCH_URL = 'https://api.pexels.com/v1/search';

    private const string RATE_LIMITER_KEY = 'stock-photos:pexels';

    public function __construct(private string $apiKey)
    {
        //
    }

    public function search(string $query, PhotoOrientation $orientation, int $count): array
    {
        $allowed = RateLimiter::attempt(
            self::RATE_LIMITER_KEY,
            config()->integer('stock-photos.rate_limit_per_hour'),
            static fn (): bool => true,
            3600,
        );

        if ($allowed === false) {
            Log::warning('stock_photos.rate_limited', ['query' => $query]);

            return [];
        }

        try {
            $response = PhotoHttp::client()
                ->withHeaders(['Authorization' => $this->apiKey])
                ->get(self::SEARCH_URL, [
                    'query' => $query,
                    'orientation' => $orientation->value,
                    'per_page' => $count,
                ]);
        } catch (ConnectionException $connectionException) {
            Log::warning('stock_photos.search_failed', ['query' => $query, 'reason' => $connectionException->getMessage()]);

            return [];
        }

        if (! $response->successful()) {
            Log::warning('stock_photos.search_failed', ['query' => $query, 'status' => $response->status()]);

            return [];
        }

        $results = $response->json('photos');
        $photos = [];

        foreach (is_array($results) ? $results : [] as $photo) {
            if (! is_array($photo)) {
                continue;
            }

            $src = is_array($photo['src'] ?? null) ? $photo['src'] : [];
            $rendition = $src['large2x'] ?? null;
            $id = $photo['id'] ?? null;

            if (! is_string($rendition) || (! is_int($id) && ! is_string($id))) {
                continue;
            }

            $photos[] = new StockPhoto(
                provider: 'pexels',
                sourceId: (string) $id,
                downloadUrl: $rendition,
                width: is_int($photo['width'] ?? null) ? $photo['width'] : 0,
                height: is_int($photo['height'] ?? null) ? $photo['height'] : 0,
                alt: is_string($photo['alt'] ?? null) ? $photo['alt'] : '',
                photographerName: is_string($photo['photographer'] ?? null) ? $photo['photographer'] : null,
                photographerUrl: is_string($photo['photographer_url'] ?? null) ? $photo['photographer_url'] : null,
                sourceUrl: is_string($photo['url'] ?? null) ? $photo['url'] : null,
            );
        }

        return $photos;
    }

    public function trackDownload(StockPhoto $photo): void
    {
        // Pexels requires no download reporting.
    }
}
