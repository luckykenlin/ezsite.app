<?php

declare(strict_types=1);

namespace App\StockPhotos;

/**
 * The shape of photo a block slot wants. Values are Pexels' own `orientation`
 * parameter values, which happen to be the generic words any provider
 * understands.
 */
enum PhotoOrientation: string
{
    case Landscape = 'landscape';

    case Portrait = 'portrait';

    case Square = 'square';

    /**
     * The shape a photo of these pixel dimensions actually has, for indexing
     * an imported photo in the shared library.
     *
     * The 5% tolerance is deliberate: a 1600×1550 photograph is square as far
     * as any layout decision goes, and classing it as landscape would make it
     * a false positive for every "I need a wide hero" search.
     */
    public static function fromDimensions(int $width, int $height): self
    {
        if ($height <= 0 || $width <= 0) {
            return self::Square;
        }

        $ratio = $width / $height;

        return match (true) {
            $ratio > 1.05 => self::Landscape,
            $ratio < 0.95 => self::Portrait,
            default => self::Square,
        };
    }
}
