<?php

declare(strict_types=1);

use App\Enums\ReviewRequestStatus;
use App\Models\Business;
use App\Models\Location;
use App\Models\ReviewRequest;
use App\Models\Tenant;

/*
 * The counter QR and the receipt link.
 *
 * The one thing in this whole feature that earns a local business customers with no
 * content from the owner and no API from anybody — and it moves review velocity,
 * which is the factor Google's own prominence documentation actually names. Google
 * Posts sit at #148 of ~187.
 */

function tenantWithReviewLink(string $placeId = 'ChIJrTLr-GyuEmsRBfy61i59si0'): array
{
    $tenant = Tenant::factory()->withDomain('acme')->create();

    $request = test()->runInTenant($tenant, function () use ($tenant, $placeId): ReviewRequest {
        $business = Business::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Jade Nails']);
        $location = Location::factory()->create([
            'tenant_id' => $tenant->id,
            'business_id' => $business->id,
            'is_primary' => true,
            'google_place_id' => $placeId,
        ]);

        return ReviewRequest::factory()->create([
            'tenant_id' => $tenant->id,
            'business_id' => $business->id,
            'location_id' => $location->id,
            'token' => 'abc123abc123',
        ]);
    });

    // A published home page, so the site counts as LIVE — otherwise a tenant with
    // nothing published answers every miss with the coming-soon page at 200 rather
    // than a 404 (bootstrap/app.php), which would make the refusals below vacuous.
    test()->createTenantPage($tenant, [['type' => 'heading', 'data' => ['content' => 'Welcome']]], slug: '/');

    return [$tenant, $request];
}

it('sends a customer straight into the review box for that branch', function (): void {
    [$tenant] = tenantWithReviewLink();

    $this->get(sprintf('http://acme.%s/r/abc123abc123', $this->centralDomain()))
        // 302, not 301: the destination depends on a mutable place id, and a
        // permanently-cached redirect would outlive a corrected one in every browser
        // that ever followed it.
        ->assertStatus(302)
        ->assertRedirect('https://search.google.com/local/writereview?placeid=ChIJrTLr-GyuEmsRBfy61i59si0');

    $stamped = ReviewRequest::query()->where('token', 'abc123abc123')->sole();

    expect($stamped->clicked_at)->not->toBeNull()
        ->and($stamped->status)->toBe(ReviewRequestStatus::Clicked)
        ->and($tenant->exists)->toBeTrue();
});

it('remembers only the first scan, so the stamp answers whether the card ever worked', function (): void {
    [$tenant] = tenantWithReviewLink();

    $this->get(sprintf('http://acme.%s/r/abc123abc123', $this->centralDomain()));
    $firstScan = ReviewRequest::query()->where('token', 'abc123abc123')->sole()->clicked_at;

    $this->travel(1)->hours();
    $this->get(sprintf('http://acme.%s/r/abc123abc123', $this->centralDomain()));

    expect(ReviewRequest::query()->where('token', 'abc123abc123')->sole()->clicked_at?->getTimestamp())
        ->toBe($firstScan?->getTimestamp())
        ->and($tenant->exists)->toBeTrue();
});

it('refuses a token printed for somebody else', function (): void {
    // The lookup is RLS-scoped because the link lives on the tenant's OWN domain,
    // so a card printed for one salon cannot resolve on another's site — which is
    // half the reason it is not a central shortener.
    tenantWithReviewLink();

    // Published, so the neighbour's own site is live and a miss is a real 404 rather
    // than its coming-soon page.
    $neighbour = Tenant::factory()->withDomain('globex')->create();
    $this->createTenantPage($neighbour, [['type' => 'heading', 'data' => ['content' => 'Globex']]], slug: '/');

    $this->get(sprintf('http://globex.%s/r/abc123abc123', $this->centralDomain()))
        ->assertNotFound();
});

it('refuses an unknown token', function (): void {
    tenantWithReviewLink();

    $this->get(sprintf('http://acme.%s/r/nothinghere', $this->centralDomain()))
        ->assertNotFound();
});

it('refuses to bounce a customer somewhere useless when the place id was cleared', function (): void {
    // The card is already printed and out in the world; a 404 is honest and the panel
    // shows the same branch as unlinked for the same reason.
    [$tenant] = tenantWithReviewLink();

    $this->runInTenant($tenant, fn (): bool => Location::query()->firstOrFail()->update(['google_place_id' => null]));

    $this->get(sprintf('http://acme.%s/r/abc123abc123', $this->centralDomain()))
        ->assertNotFound();
});
