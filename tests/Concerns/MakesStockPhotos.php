<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\StockPhotos\PhotoOrientation;
use App\StockPhotos\StockPhoto;
use App\StockPhotos\StockPhotoProvider;

/**
 * Shared doubles and fixtures for the photo pipeline, mixed into the whole suite
 * by tests/Pest.php.
 *
 * Here rather than as free functions in a test file because several files need
 * the same provider double, and two files declaring `trackingStockProvider()`
 * would be a fatal redeclaration the moment the suite loads both.
 */
trait MakesStockPhotos
{
    /**
     * A provider that records download tracking — the compliance hook an
     * Unsplash driver will rely on, so an import must actually call it. Its
     * `search()` finds nothing: use {@see poolStockPhotoProvider()} when the
     * test is about searching.
     */
    protected function trackingStockPhotoProvider(): StockPhotoProvider
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

    /**
     * A provider returning a fixed pool of photos and recording every search, so
     * budget and query assertions need no HTTP.
     */
    protected function poolStockPhotoProvider(int $photos = 6): StockPhotoProvider
    {
        return new class($photos) implements StockPhotoProvider
        {
            /** @var list<array{query: string, count: int}> */
            public array $searches = [];

            public function __construct(private readonly int $photos)
            {
                //
            }

            public function search(string $query, PhotoOrientation $orientation, int $count): array
            {
                $this->searches[] = ['query' => $query, 'count' => $count];

                $pool = [];

                // Honours $count the way PexelsProvider does (it passes it as
                // `per_page`). A double that returned its whole pool regardless
                // would let a caller look like it had over-fetched when it had
                // asked for exactly what it needed.
                for ($index = 1; $index <= min($this->photos, $count); $index++) {
                    $pool[] = new StockPhoto(
                        provider: 'pexels',
                        sourceId: (string) $index,
                        downloadUrl: 'https://images.pexels.com/photos/'.$index.'/photo.jpeg',
                        width: 4000,
                        height: 2667,
                        alt: 'Stock photo '.$index,
                    );
                }

                return $pool;
            }

            public function trackDownload(StockPhoto $photo): void
            {
                //
            }
        };
    }

    /**
     * One provider search result, with the query-string-bearing download URL a
     * real CDN hands back.
     */
    protected function stockPhoto(string $sourceId = '123'): StockPhoto
    {
        return new StockPhoto(
            provider: 'pexels',
            sourceId: $sourceId,
            downloadUrl: 'https://images.pexels.com/photos/'.$sourceId.'/photo.jpeg?auto=compress&fm=jpg',
            width: 4000,
            height: 2667,
            alt: 'An espresso machine on a cafe counter',
            photographerName: 'Jane Doe',
            photographerUrl: 'https://www.pexels.com/@jane',
            sourceUrl: 'https://www.pexels.com/photo/'.$sourceId.'/',
        );
    }

    /**
     * A 200x200 WebP of deterministic per-pixel noise, encoded at the given
     * quality.
     *
     * Noise so that it does not compress away, and WebP so a caller can hand
     * `OptimizeImage` something already more compressed than its own quality
     * setting — the one real input for which re-encoding costs bytes instead
     * of saving them.
     */
    protected function noisyWebp(int $quality): string
    {
        $image = imagecreatetruecolor(200, 200);

        for ($x = 0; $x < 200; $x++) {
            for ($y = 0; $y < 200; $y++) {
                imagesetpixel($image, $x, $y, (int) imagecolorallocate($image, ($x * 7 + $y * 13) % 256, ($x * 29) % 256, ($y * 53) % 256));
            }
        }

        ob_start();
        imagewebp($image, null, $quality);

        return ob_get_clean();
    }

    /**
     * A PNG of solid horizontal bands, top to bottom, each colour taking an
     * equal share of the height — so the dominant colour and the overall
     * brightness of the image are known before any palette extraction runs.
     *
     * Square unless `$width` says otherwise. A landscape fixture matters where
     * the code under test records an orientation or a downscale: a square one
     * makes `PhotoOrientation::Square` the only answer it can give, and a
     * scale-to-width assertion vacuous.
     *
     * @param  list<array{int, int, int}>  $bands
     */
    protected function bandedPng(array $bands, int $size = 64, ?int $width = null): string
    {
        $width ??= $size;
        $image = imagecreatetruecolor($width, $size);
        $bandHeight = intdiv($size, count($bands));

        foreach ($bands as $index => [$red, $green, $blue]) {
            imagefilledrectangle(
                $image,
                0,
                $index * $bandHeight,
                $width - 1,
                $index === count($bands) - 1 ? $size - 1 : ($index + 1) * $bandHeight - 1,
                (int) imagecolorallocate($image, $red, $green, $blue),
            );
        }

        ob_start();
        imagepng($image);

        return ob_get_clean();
    }
}
