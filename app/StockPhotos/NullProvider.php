<?php

declare(strict_types=1);

namespace App\StockPhotos;

/**
 * The provider bound when no stock-photo API key is configured: finds
 * nothing, reports nothing. Every consumer already treats an empty search
 * result as "ship without photos", so binding this instead of throwing at
 * resolution time is what keeps keyless environments (local, CI, a tenant
 * install that never wants stock photos) running the exact pre-pipeline
 * behavior with zero configuration.
 */
final readonly class NullProvider implements StockPhotoProvider
{
    public function search(string $query, PhotoOrientation $orientation, int $count): array
    {
        return [];
    }

    public function trackDownload(StockPhoto $photo): void
    {
        //
    }
}
