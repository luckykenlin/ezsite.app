<?php

declare(strict_types=1);

use App\Filament\Tenant\Pages\AskForReviews;
use App\Models\Business;
use App\Models\Location;
use App\Models\ReviewRequest;
use Livewire\Livewire;

/**
 * A branch on the signed-in tenant, with or without a Google place id.
 */
function reviewBranch(?string $placeId, string $label = 'Downtown'): Location
{
    $business = Business::query()->firstOr(fn (): Business => Business::factory()->create([
        'tenant_id' => test()->tenant->id,
    ]));

    return Location::factory()->create([
        'tenant_id' => test()->tenant->id,
        'business_id' => $business->id,
        'is_primary' => true,
        'label' => $label,
        'google_place_id' => $placeId,
    ]);
}

it('hands the operator a printable link and QR per branch', function (): void {
    $location = reviewBranch('ChIJplace');

    Livewire::test(AskForReviews::class)
        ->assertOk()
        ->assertSee('Downtown')
        // An SVG payload, because this gets printed and has to survive being
        // blown up to fill an A5 card. Matched on the QR's own module markup rather
        // than on `<svg`, which the panel's icons emit everywhere.
        ->assertSee('class="qr-', false)
        ->assertSee('Nobody has opened it yet');

    $request = ReviewRequest::query()->where('location_id', $location->id)->sole();

    expect($request->token)->toHaveLength(12);
});

it('says what is missing instead of showing a link that goes nowhere', function (): void {
    // A "review us" button pointing at a search results page is worse than no button.
    reviewBranch(null);

    // Asserted on the copy, not on the absence of `<svg` — the panel's own chrome is
    // full of inline heroicons.
    Livewire::test(AskForReviews::class)
        ->assertOk()
        ->assertSee('no Google place ID yet')
        ->assertSee('Add it on the branch')
        ->assertDontSee('Nobody has opened it yet');

    expect(ReviewRequest::query()->count())->toBe(0);
});

it('reports a card somebody has actually used', function (): void {
    $location = reviewBranch('ChIJplace');

    ReviewRequest::factory()->clicked()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $location->business_id,
        'location_id' => $location->id,
    ]);

    Livewire::test(AskForReviews::class)
        ->assertOk()
        ->assertSee('Somebody has used this one');
});

it('states the rules where the operator is about to print the card', function (): void {
    // Not boilerplate: review gating is banned outright by Yelp and incentivised
    // reviews by Google, and an operator who does either can lose the profile this
    // feature exists to help. Where they are about to act is the only place it reads.
    reviewBranch('ChIJplace');

    Livewire::test(AskForReviews::class)
        ->assertOk()
        ->assertSee('Ask every customer')
        ->assertSee('Never offer a discount or a freebie for a review');
});

it('reuses one link across renders rather than minting a row per visit', function (): void {
    $location = reviewBranch('ChIJplace');

    Livewire::test(AskForReviews::class)->assertOk();
    Livewire::test(AskForReviews::class)->assertOk();

    expect(ReviewRequest::query()->where('location_id', $location->id)->count())->toBe(1);
});
