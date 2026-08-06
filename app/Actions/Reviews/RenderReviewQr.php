<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\HtmlString;

/**
 * A review link as an inline SVG QR code.
 *
 * SVG rather than a PNG file on disk: this is printed, so it has to survive
 * being blown up to fill an A5 card, and nothing needs to persist — the link
 * is the durable thing and the QR is just a rendering of it.
 *
 * Error correction stays at the lowest level on purpose. A URL this short
 * encodes into a coarse grid, and a coarse grid is what scans reliably from a
 * counter in bad light; heavier correction buys redundancy nobody needs and
 * costs the module size that actually matters.
 *
 * Rendered with `chillerlan/php-qrcode`, which arrives as a direct
 * requirement of `filament/filament` (it backs the panel's authenticator-app
 * setup) — so this adds no dependency. If Filament ever drops it, promote it
 * to a direct require rather than reaching for a different encoder.
 *
 * Returns an {@see HtmlString} so views echo it with `{{ }}` — the "this
 * markup is trusted" judgement lives here, on the class that builds it, not
 * as a `{!! !!}` in a template. It is generated markup, not tenant-authored
 * content: the library emits its own SVG and the only variable in it is a
 * URL this app built.
 */
final readonly class RenderReviewQr
{
    public function handle(string $url): HtmlString
    {
        $svg = new QRCode(new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'eccLevel' => EccLevel::L,
            'outputBase64' => false,
            'svgUseFillAttributes' => false,
            'addQuietzone' => true,
        ]))->render($url);

        // render() is documented as returning the output interface's own type, which
        // the library types as mixed; QRMarkupSVG returns markup.
        return new HtmlString(is_string($svg) ? $svg : '');
    }
}
