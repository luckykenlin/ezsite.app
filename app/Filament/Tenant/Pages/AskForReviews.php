<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Actions\Reviews\BuildReviewCards;
use App\Filament\Tenant\Resources\Locations\LocationResource;
use App\Models\Location;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Computed;

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
     * One card per branch, built by {@see BuildReviewCards}.
     *
     * Computed, so the loop that mints links and encodes QR SVGs runs once per
     * request rather than on every Livewire re-render (collapsing the rules
     * section was re-querying every branch before this).
     *
     * @return list<array{location: Location, url: string|null, qr: HtmlString|null, clicked: bool}>
     */
    #[Computed]
    public function cards(): array
    {
        return resolve(BuildReviewCards::class)->handle();
    }

    public function settingsUrl(): string
    {
        return LocationResource::getUrl('index');
    }
}
