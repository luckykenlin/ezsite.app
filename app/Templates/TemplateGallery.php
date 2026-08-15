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
 * (`scripts/capture-template-screenshots.mjs`), not live iframes. An iframe
 * per card is a full page load per card, which is unusable on a phone,
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
     * Three capture widths, because the cards and the detail hero want very
     * different images and were being served the same one.
     *
     *  - CARD is what the gallery grid and the landing page's hero fan use.
     *    They render between 290 and 400 CSS px inside `max-w-7xl`, so the
     *    1440 they used to load was four times the pixels of the slot — a
     *    bigger waste than the file format ever was.
     *  - DESKTOP is the template detail page's hero, which really does render
     *    at ~672 CSS px and therefore wants 1440 on a 2x screen.
     *  - MOBILE is the "reads well on a phone" pairing. 780, not 390: it is
     *    displayed at 390 CSS px, so a 390px capture was soft on every retina
     *    screen it has ever been shown on.
     */
    public const int CARD_WIDTH = 800;

    public const int DESKTOP_WIDTH = 1440;

    public const int MOBILE_WIDTH = 780;

    /**
     * The public URL of a captured screenshot, or null when nobody has
     * captured one.
     */
    public function screenshot(SiteTemplate $template, int $width = self::CARD_WIDTH): ?string
    {
        $path = $this->screenshotPath($template, $width);

        return file_exists(public_path($path)) ? asset($path) : null;
    }

    /**
     * The path a capture is written to — shared with the capture script, and
     * the reason it is a method rather than two string literals.
     */
    public function screenshotPath(SiteTemplate $template, int $width = self::CARD_WIDTH): string
    {
        return sprintf('%s/%s-%d.%s', self::SCREENSHOT_DIRECTORY, $template->value, $width, $this->extension($width));
    }

    /**
     * WebP everywhere except the desktop shot, which stays JPEG because it is
     * also the `og:image` on every template detail page — social crawlers are
     * still uneven about WebP, and a link preview that silently stops
     * rendering is a bad way to find that out. It is one image on one page, so
     * its weight barely matters; the cards are where the bytes are.
     */
    public function extension(int $width): string
    {
        return $width === self::DESKTOP_WIDTH ? 'jpg' : 'webp';
    }

    /**
     * The live demo site's URL.
     *
     * Composed from the app URL rather than read off the tenant's `domains`
     * row, so the gallery renders with no database round trip per card — and
     * still renders before `demo:seed` has ever run, which is what a fresh
     * clone looks like.
     *
     * The SCHEME is the one part that cannot come from the app URL alone. The
     * detail page embeds this in an `<iframe>`, and a browser silently blocks an
     * insecure frame inside a secure page — so an installation served over HTTPS
     * with an `http://` APP_URL (every Herd/Valet machine in this project, and
     * any deployment behind a TLS proxy that forgot the env) shows a blank panel
     * where the demo should be, with nothing wrong in the markup for a request
     * test to catch. Either source saying "secure" wins, which cannot downgrade
     * an HTTPS deployment when this is called with no real request (a console
     * command, a queued job) the way reading the request alone would.
     */
    public function demoUrl(SiteTemplate $template): string
    {
        $appUrl = uri(Config::string('app.url'));
        $secure = request()->isSecure() || $appUrl->scheme() === 'https';

        return sprintf('%s://%s.%s/', $secure ? 'https' : 'http', $template->demoSubdomain(), $appUrl->host());
    }
}
