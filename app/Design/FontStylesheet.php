<?php

declare(strict_types=1);

namespace App\Design;

use Illuminate\Support\HtmlString;

/**
 * A `<link>` to the built stylesheet declaring every font family, for the
 * design surfaces that must draw a specimen of each.
 *
 * Deliberately NOT `Vite::fonts()`, which is the obvious call and the wrong one
 * here. For all nine families it emits ~111KB of HTML: several hundred
 * `<link rel="preload">` tags — one per family/weight/subset — plus the whole
 * `@font-face` sheet inline. The preloads alone would eagerly fetch every woff2
 * in the bundle, and both halves would ride in EVERY Livewire response for the
 * page editor, because a component's markup is re-sent on each round trip. One
 * click on a swatch would carry 111KB back with it.
 *
 * The built sheet has `font-display: swap` and `unicode-range` on each face, so
 * linking it fetches nothing until a specimen actually renders in that family,
 * and the browser caches it across renders and page loads. The tag is ~80 bytes.
 *
 * `Vite::asset()` cannot resolve it: that looks up SOURCE keys in the main
 * manifest, and this file is an emitted chunk. The fonts manifest names its
 * output path directly, which is what is read here.
 *
 * No hot-mode branch. Under `npm run dev` the fonts manifest still describes the
 * last build, and if there has never been one this returns nothing and the
 * specimens fall back to their generic stacks — which is exactly what the admin
 * panel did before this existed. A missing build degrades the specimens; it does
 * not break the page.
 */
final class FontStylesheet
{
    private const string MANIFEST = 'build/fonts-manifest.json';

    /**
     * `$manifestPath` is the seam for tests: the interesting branches here are
     * all "the manifest is missing or says nothing useful", and reaching them by
     * moving the real file aside would race the other workers in a parallel run.
     */
    public static function link(?string $manifestPath = null): HtmlString
    {
        $file = self::file($manifestPath ?? public_path(self::MANIFEST));

        return new HtmlString(
            $file === null ? '' : '<link rel="stylesheet" href="'.e(asset('build/'.$file)).'" />',
        );
    }

    /**
     * The stylesheet's path relative to the build directory, or null when there
     * is no usable manifest.
     */
    private static function file(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $manifest = json_decode((string) file_get_contents($path), true);
        $style = is_array($manifest) ? ($manifest['style'] ?? null) : null;
        $file = is_array($style) ? ($style['file'] ?? null) : null;

        return is_string($file) ? $file : null;
    }
}
