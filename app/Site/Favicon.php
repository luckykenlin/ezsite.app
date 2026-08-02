<?php

declare(strict_types=1);

namespace App\Site;

use App\Design\ColorPalette;
use App\Models\Business;
use Illuminate\Support\HtmlString;

/**
 * The tenant site's browser-tab icon.
 *
 * Until this existed every customer's site shared one favicon — Fabricator's
 * global config value — so eight tabs open on eight of our sites were
 * indistinguishable, including to the owners looking at their own.
 *
 * Two sources, in order: the logo the owner uploaded, or failing that a letter
 * mark generated from the business name in its own brand colour. The generated
 * one matters more than it sounds — it means a site has a distinct icon from the
 * minute it is provisioned, with nothing to upload, which is the state most
 * sites are in (see {@see OnboardingTask::Logo}).
 *
 * Sibling of {@see \App\Design\ThemeVariables}: both compile a business into
 * `<head>` markup, and both are emitted by the same Fabricator render hook.
 *
 * Fabricator's base layout emits its own `<link rel="icon" href="favicon.ico">`
 * before this, and that is left alone deliberately rather than suppressed. The
 * result is the canonical two-line favicon pattern — a raster default first, the
 * richer icon after it — so a browser too old for an SVG icon falls back to the
 * platform one, and a tenant with no Business profile (for whom the render hook
 * never calls this at all) still gets something.
 */
final class Favicon
{
    /**
     * The generated mark's colour when the business has no valid brand hex —
     * the tenant panel's primary, so an unbranded site still looks deliberate.
     */
    private const string DEFAULT_COLOR = '#059669';

    public static function links(Business $business): HtmlString
    {
        $logoUrl = $business->logoUrl();

        if ($logoUrl !== null) {
            return new HtmlString(sprintf('<link rel="icon" href="%s">', e($logoUrl)));
        }

        // The SVG is the modern icon; the generated PNG-less fallback is
        // deliberately omitted in favour of `rel="icon"` alone, because a
        // browser too old for an SVG favicon simply shows its default glyph —
        // which is exactly what every one of these sites had before.
        return new HtmlString(sprintf(
            '<link rel="icon" type="image/svg+xml" href="%s">',
            self::letterMark($business),
        ));
    }

    /**
     * A rounded square in the brand colour with the business's first character
     * on it, as a data URI.
     *
     * `rawurlencode` rather than base64: it keeps the markup readable in a
     * page's source, and the encoding is what makes the `#` of the hex colour
     * safe inside an `href` (unencoded it would truncate the URI at the
     * fragment). The character itself is escaped as XML text on the way in, so a
     * business called `<script>` cannot reach the document as markup.
     *
     * `mb_substr` because the first character is frequently not ASCII — a CJK
     * restaurant name renders as its own glyph here, which is a better mark than
     * any transliteration would be.
     */
    private static function letterMark(Business $business): string
    {
        $character = e(mb_strtoupper(mb_substr(mb_trim($business->name), 0, 1)));
        $color = ColorPalette::validHex($business->brand_primary) ?? self::DEFAULT_COLOR;

        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="{$color}"/><text x="32" y="45" text-anchor="middle" font-family="system-ui,-apple-system,'Segoe UI',sans-serif" font-size="38" font-weight="700" fill="#ffffff">{$character}</text></svg>
            SVG;

        return 'data:image/svg+xml,'.rawurlencode($svg);
    }
}
