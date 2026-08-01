<?php

declare(strict_types=1);

namespace App\StockPhotos;

/**
 * One search result from a {@see StockPhotoProvider}, in provider-neutral
 * terms. Carries everything the importer needs so no second API call is ever
 * required: the download URL, the pixel dimensions (Pexels reports them, so
 * the imported file never needs decoding), and the provenance/credit fields
 * that persist onto the media record.
 */
final readonly class StockPhoto
{
    public function __construct(
        public string $provider,
        public string $sourceId,
        public string $downloadUrl,
        public int $width,
        public int $height,
        public string $alt,
        public ?string $photographerName = null,
        public ?string $photographerUrl = null,
        public ?string $sourceUrl = null,
    ) {
        //
    }
}
