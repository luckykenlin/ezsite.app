<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Models\Location;
use App\Models\ReviewRequest;
use App\Site\PublicUrl;
use Illuminate\Support\HtmlString;

/**
 * The "Ask for reviews" page's view model: one card per branch — the tracked
 * link, the QR to print, and (where the place id is missing) nothing, so the
 * page can say what to do about it.
 *
 * Minting links on read is deliberate: the page IS the sending layer for the
 * counter-card channel, and a durable link per branch has to exist by the
 * time the owner prints it. The caller is expected to memoize per request
 * (the page uses a Livewire computed property), so a Livewire re-render does
 * not re-run the find-or-create loop or re-encode every QR.
 */
final readonly class BuildReviewCards
{
    public function __construct(
        private BuildReviewLink $links,
        private RenderReviewQr $qr,
    ) {
        //
    }

    /**
     * @return list<array{location: Location, url: string|null, qr: HtmlString|null, clicked: bool}>
     */
    public function handle(): array
    {
        return array_values(Location::query()
            ->primaryFirst()
            ->get()
            ->map(function (Location $location): array {
                $request = $this->links->handle($location);
                $url = $request instanceof ReviewRequest ? PublicUrl::to($request) : null;

                return [
                    'location' => $location,
                    'url' => $url,
                    'qr' => $url === null ? null : $this->qr->handle($url),
                    'clicked' => $request instanceof ReviewRequest && $request->clicked_at !== null,
                ];
            })
            ->all());
    }
}
