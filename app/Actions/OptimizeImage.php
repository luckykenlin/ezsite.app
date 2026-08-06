<?php

declare(strict_types=1);

namespace App\Actions;

use App\Images\OptimizedImage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * The one gate every image passes through before it is written to a disk.
 *
 * It exists because the bytes this product stores are not the bytes it should
 * serve. A Pexels `large2x` rendition is the right SIZE for a hero — 1880px —
 * and arrives near-lossless at 2 to 3 MB; a photograph off someone's phone is
 * 12 megapixels and 8 MB. Both were being written verbatim to a tenant disk
 * and handed straight to `<img src>` by {@see \App\Site\MediaResolver}, which
 * does no transformation at all. One demo home page was shipping 4.6 MB of
 * images, 3.3 MB of it a single hero.
 *
 * Downscale AND re-encode, because they fix different halves: the scale caps a
 * phone upload's pixel count, and the WebP pass is what turns a near-lossless
 * 1880px JPEG into something a phone can load — the same photograph at the
 * same dimensions, at roughly a fifteenth of the bytes.
 *
 * Applied at WRITE time, never at render. Glide is present (Curator uses it for
 * panel thumbnails) but its source resolves through the tenant-suffixed
 * `storage_path('app')`, which is why {@see Library\AdoptLibraryPhoto}
 * copies bytes onto the tenant disk instead of sharing them, and why
 * `TenancyServiceProvider` has to wrap Curator's Glide route in tenancy
 * middleware. Transforming once, on the way in, keeps every read a plain file
 * read.
 *
 * GD rather than Imagick, matching {@see Library\ExtractPhotoPalette}:
 * it is the driver present everywhere.
 *
 * NEVER THROWS, and never returns bytes bigger than it was given. An image
 * this cannot read, or cannot improve, passes through untouched — losing a
 * customer's logo to a decoder edge case would be a far worse outcome than
 * storing it at its original weight.
 */
final readonly class OptimizeImage
{
    /**
     * @param  int|null  $maxWidth  overrides `images.max_width` for a caller
     *                              with a tighter ceiling than a full-bleed hero
     */
    public function handle(string $bytes, ?int $maxWidth = null): OptimizedImage
    {
        try {
            $image = ImageManager::gd()
                ->read($bytes)
                ->scaleDown(width: $maxWidth ?? Config::integer('images.max_width'));

            $encoded = (string) $image->toWebp(Config::integer('images.quality'));

            // An already-small PNG can come out of a WebP encoder LARGER than
            // it went in. Optimising is not a promise we can keep for every
            // input, so where it is not kept, do nothing.
            if (mb_strlen($encoded, '8bit') < mb_strlen($bytes, '8bit')) {
                return new OptimizedImage($encoded, $image->width(), $image->height(), 'webp', 'image/webp');
            }
        } catch (Throwable $throwable) {
            Log::warning('image.optimize_failed', ['reason' => $throwable->getMessage()]);
        }

        return $this->passthrough($bytes);
    }

    /**
     * The original bytes, described.
     *
     * `getimagesizefromstring()` rather than another decode: it reads the
     * header only, so describing an image this action has just failed to
     * process cannot fail the same way twice. A file it cannot read either is
     * reported as a zero-dimension `application/octet-stream` — the caller
     * still stores it, and a wrong extension on a file nothing could decode
     * costs nothing.
     */
    private function passthrough(string $bytes): OptimizedImage
    {
        $size = @getimagesizefromstring($bytes);

        if ($size === false) {
            return new OptimizedImage($bytes, 0, 0, 'bin', 'application/octet-stream');
        }

        // A lookup rather than a `match`: it is a pure mapping with no logic in
        // any arm, and xdebug attributes match arms to their own lines, which
        // under `--parallel` reports untaken arms as uncovered even when the
        // mapping is fully exercised.
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
        ];

        return new OptimizedImage($bytes, $size[0], $size[1], $extensions[$size['mime']] ?? 'bin', $size['mime']);
    }
}
