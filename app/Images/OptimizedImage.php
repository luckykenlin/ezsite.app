<?php

declare(strict_types=1);

namespace App\Images;

/**
 * One image's bytes as they will actually be written to disk, together with
 * the facts a `media`/`library_photos` row needs to describe them.
 *
 * The dimensions here are the PROCESSED image's, which is the whole reason
 * this carries them rather than leaving callers to their input: before this
 * existed, the library recorded the dimensions Pexels reported for the
 * ORIGINAL photograph — averaging 5766px — while the file on disk was the
 * 1880px rendition that had actually been downloaded. Nothing rendered those
 * numbers, so nothing broke, but every row was wrong.
 */
final readonly class OptimizedImage
{
    public function __construct(
        public string $bytes,
        public int $width,
        public int $height,
        public string $extension,
        public string $mimeType,
    ) {
        //
    }

    /**
     * The stored byte count, which is what a `size` column means.
     *
     * '8bit' is required, not decorative: pint.json turns on mb_str_functions,
     * so a plain strlen() here gets rewritten to a character count that
     * under-reports every image's size.
     */
    public function size(): int
    {
        return mb_strlen($this->bytes, '8bit');
    }
}
