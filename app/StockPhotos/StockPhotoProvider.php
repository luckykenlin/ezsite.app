<?php

declare(strict_types=1);

namespace App\StockPhotos;

/**
 * A searchable source of licensed stock photography.
 *
 * The seam that keeps the photo pipeline provider-agnostic: consumers
 * ({@see \App\Actions\Pages\PopulateDraftImages},
 * {@see \App\Actions\FindOrImportStockPhoto}) speak only this interface, and
 * {@see \App\Providers\AppServiceProvider} binds {@see PexelsProvider} when a
 * key is configured or {@see NullProvider} otherwise — so every environment
 * without credentials runs the pipeline inert-but-safe.
 *
 * BEFORE ADDING AN UNSPLASH DRIVER, know what its API guidelines demand that
 * Pexels does not: photos must be HOTLINKED from Unsplash's CDN (not imported
 * to tenant disks), every use must fire the photo's `download_location` ping
 * ({@see trackDownload()} is the hook), and photographer attribution must
 * RENDER on the page, not just sit in the media record. That is a rendering
 * change, not just a client class — a deliberate gate, not an oversight.
 */
interface StockPhotoProvider
{
    /**
     * Search for photos. Implementations return an empty list on ANY failure
     * (network, auth, rate limit) rather than throwing: a draft without
     * photos is a degraded success, never a failed generation.
     *
     * @return list<StockPhoto>
     */
    public function search(string $query, PhotoOrientation $orientation, int $count): array;

    /**
     * Report that a photo was actually used. A no-op for Pexels; the
     * compliance hook an Unsplash driver will need.
     */
    public function trackDownload(StockPhoto $photo): void;
}
