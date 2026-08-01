<?php

declare(strict_types=1);

namespace App\StockPhotos;

/**
 * The colour metadata of one photograph, as extracted by
 * {@see \App\Actions\Library\ExtractPhotoPalette} and persisted onto a
 * {@see \App\Models\LibraryPhoto}.
 *
 * `isDark` is the load-bearing field, not `swatches`: it answers "can white
 * text sit on top of this?", which is what decides whether a photo may be used
 * as a `full-bleed-overlay` hero or a `full-photo` cta background. It is
 * measured over the WHOLE sampled image rather than from the dominant swatch,
 * because a photo can be dominated by a bright sky and still be unusable
 * behind text.
 */
final readonly class PhotoPalette
{
    /**
     * @param  list<string>  $swatches  the most common colours, `#rrggbb`, dominant first
     */
    public function __construct(
        public array $swatches,
        public string $dominant,
        public bool $isDark,
    ) {
        //
    }
}
