<?php

declare(strict_types=1);

namespace App\Site;

/**
 * Whether a stored URL would run code when a browser follows it.
 *
 * Block data reaches `href`/`src` through Blade's `{{ }}`, which escapes the
 * VALUE but does nothing about the SCHEME — `javascript:alert(1)` in a `cta_url`
 * renders as a live link. Every authoring path feeds those fields:
 * {@see \App\Ai\BlockDataSanitizer} only `strip_tags()`es leaves (a no-op on a
 * scheme), the panel's {@see \App\Filament\Fabricator\Fields\LinkInput}
 * deliberately allows relative paths and anchors so it cannot use Laravel's
 * `url` rule, and seeders write raw arrays.
 *
 * A DENY-list, not an allow-list. An allow-list would have to enumerate every
 * legitimate shape (relative, anchor, query-only, protocol-relative, mailto,
 * tel, http, https) and silently blanks whatever it forgot; naming the three
 * schemes that execute is the narrow, auditable rule.
 */
final class UrlScheme
{
    /**
     * The schemes a browser executes rather than fetches.
     *
     * `data:` is denied outright, `data:image` included: an SVG cannot script in
     * `<img src>`, but the same value in an `href` navigates to a document where
     * it can, and a key name does not distinguish the two contexts —
     * `images[].url` and `nav_links[].url` are both spelled `url`.
     *
     * @var list<string>
     */
    private const array EXECUTABLE = ['javascript:', 'vbscript:', 'data:'];

    /**
     * Control characters and whitespace are stripped BEFORE the scheme is read,
     * because browsers do the same: `java&#9;script:alert(1)` and a value with a
     * leading newline both navigate, so a plain prefix check misses them.
     */
    public static function isExecutable(string $url): bool
    {
        $bare = mb_strtolower((string) preg_replace('/[\x00-\x20\x7f]+/', '', $url));

        return array_any(self::EXECUTABLE, fn (string $scheme): bool => str_starts_with($bare, $scheme));
    }
}
