<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Actions\Reviews\BuildReviewLink;
use App\Filament\Tenant\Resources\Locations\LocationResource;
use App\Models\Location;
use App\Models\ReviewRequest;
use BackedEnum;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * The counter card: a tracked review link per branch, and a QR to print.
 *
 * This is the highest-leverage screen in the whole updates feature and the cheapest
 * to build, which is worth saying out loud because it looks like the smallest.
 * Review quantity, velocity and rating sit inside Google's own prominence factor —
 * the thing that actually decides whether a salon appears in the map pack. Google
 * Posts, by contrast, sit at #148 of ~187 in Whitespark's 2026 study. And unlike
 * every other surface here, this asks the owner for NO CONTENT: they print one
 * square and put it by the till.
 *
 * It also needs no API and no approval, which is why it ships now rather than with
 * the Business Profile connector.
 *
 * The QR is rendered with `chillerlan/php-qrcode`, which arrives as a direct
 * requirement of `filament/filament` (it backs the panel's authenticator-app setup)
 * — so this adds no dependency. If Filament ever drops it, promote it to a direct
 * require rather than reaching for a different encoder.
 */
final class AskForReviews extends Page
{
    protected string $view = 'filament.tenant.pages.ask-for-reviews';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?string $title = 'Ask for reviews';

    /**
     * Above the popup settings: a review is worth more than a captured email, and
     * the ordering should say so.
     */
    protected static ?int $navigationSort = 1;

    /**
     * One card per branch: the link, the QR, and — where the place id is missing —
     * what to do about it.
     *
     * @return list<array{location: Location, url: string|null, qr: string|null, clicked: bool, settingsUrl: string}>
     */
    public function cards(): array
    {
        $links = resolve(BuildReviewLink::class);

        return array_values(Location::query()
            ->primaryFirst()
            ->get()
            ->map(function (Location $location) use ($links): array {
                $request = $links->handle($location);
                $url = $request === null ? null : url($request->getUrl());

                return [
                    'location' => $location,
                    'url' => $url,
                    'qr' => $url === null ? null : $this->qr($url),
                    'clicked' => $request instanceof ReviewRequest && $request->clicked_at !== null,
                    'settingsUrl' => LocationResource::getUrl('index'),
                ];
            })
            ->all());
    }

    /**
     * The link as an inline SVG data URI.
     *
     * SVG rather than a PNG file on disk: this is printed, so it has to survive being
     * blown up to fill an A5 card, and nothing needs to persist — the link is the
     * durable thing and the QR is just a rendering of it.
     *
     * Error correction stays at the lowest level on purpose. A URL this short encodes
     * into a coarse grid, and a coarse grid is what scans reliably from a counter in
     * bad light; heavier correction buys redundancy nobody needs and costs the module
     * size that actually matters.
     */
    private function qr(string $url): string
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
        return is_string($svg) ? $svg : '';
    }
}
