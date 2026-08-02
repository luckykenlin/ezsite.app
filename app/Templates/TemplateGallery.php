<?php

declare(strict_types=1);

namespace App\Templates;

use Illuminate\Support\Facades\Config;

/**
 * What the public gallery needs to know about a template that the template
 * itself has no business knowing: where its demo site lives, and whether
 * anyone has captured a screenshot of it yet.
 *
 * Screenshots are PRE-RENDERED and committed
 * (`scripts/capture-template-screenshots.mjs`), not live iframes. Eight
 * iframes on one page is eight full page loads, which is unusable on a phone,
 * and the signed preview URLs the editor uses expire in seven days — no good
 * for a permanent gallery. The detail page does embed one iframe, lazily and
 * below the fold, where a visitor has already chosen to look closely.
 *
 * A MISSING screenshot is a normal state, not an error: a fresh clone has no
 * captures, and the card falls back to a brand-coloured panel drawn from the
 * template's own hexes. The gallery is therefore never broken by an asset
 * nobody has generated — running the capture script upgrades it.
 */
final readonly class TemplateGallery
{
    /**
     * Where captures are written and read from, relative to `public/`. The
     * capture script hard-codes the same path; it is one directory, and a
     * config knob would only be a way for the two halves to disagree.
     */
    public const string SCREENSHOT_DIRECTORY = 'images/templates';

    /**
     * JPEG, not PNG. These are photographs of photographs — the first capture
     * run produced 52 MB of lossless screenshots for sixteen files, which is
     * not something to put in a git history for images that render at a third
     * of their captured width.
     */
    public const string SCREENSHOT_EXTENSION = 'jpg';

    /**
     * The two capture widths: a desktop shot for the cards and the detail
     * hero, and a phone shot for the "it works on a phone too" pairing.
     */
    public const int DESKTOP_WIDTH = 1440;

    public const int MOBILE_WIDTH = 390;

    /**
     * The public URL of a captured screenshot, or null when nobody has
     * captured one.
     */
    public function screenshot(SiteTemplate $template, int $width = self::DESKTOP_WIDTH): ?string
    {
        $path = $this->screenshotPath($template, $width);

        return file_exists(public_path($path)) ? asset($path) : null;
    }

    /**
     * The path a capture is written to — shared with the capture script, and
     * the reason it is a method rather than two string literals.
     */
    public function screenshotPath(SiteTemplate $template, int $width = self::DESKTOP_WIDTH): string
    {
        return sprintf('%s/%s-%d.%s', self::SCREENSHOT_DIRECTORY, $template->value, $width, self::SCREENSHOT_EXTENSION);
    }

    /**
     * The live demo site's URL.
     *
     * Composed from the app URL rather than read off the tenant's `domains`
     * row, so the gallery renders with no database round trip per card — and
     * still renders before `demo:seed` has ever run, which is what a fresh
     * clone looks like.
     */
    public function demoUrl(SiteTemplate $template): string
    {
        $appUrl = uri(Config::string('app.url'));

        return sprintf('%s://%s.%s/', $appUrl->scheme() ?? 'http', $template->demoSubdomain(), $appUrl->host());
    }
}
