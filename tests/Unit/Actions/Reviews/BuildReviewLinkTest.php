<?php

declare(strict_types=1);

use App\Actions\Reviews\BuildReviewLink;
use App\Models\Business;
use App\Models\Location;
use App\Models\ReviewRequest;
use App\Models\Tenant;

/**
 * A branch with, or without, a Google place id.
 */
function branch(Tenant $tenant, ?string $placeId): Location
{
    $business = Business::factory()->create(['tenant_id' => $tenant->id]);

    return Location::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'is_primary' => true,
        'google_place_id' => $placeId,
    ]);
}

it('mints one durable link per branch rather than a row per page load', function (): void {
    // A QR taped to a till is handed out a thousand times and has to keep working. A
    // token per ASK is what a sending layer needs, and there isn't one.
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function () use ($tenant): void {
        $location = branch($tenant, 'ChIJplace');
        $links = resolve(BuildReviewLink::class);

        $first = $links->handle($location);
        $second = $links->handle($location);

        expect($second?->id)->toBe($first?->id)
            ->and(ReviewRequest::query()->count())->toBe(1)
            ->and($first?->getUrl())->toBe('/r/'.$first->token);
    });
});

it('refuses rather than guessing when a branch has no place id', function (): void {
    // A "review us" button pointing at a search results page is worse than no button:
    // the customer has to find the business themselves, and most give up.
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function () use ($tenant): void {
        expect(resolve(BuildReviewLink::class)->handle(branch($tenant, null)))->toBeNull()
            ->and(ReviewRequest::query()->count())->toBe(0);
    });
});

it('gives each branch its own link', function (): void {
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function () use ($tenant): void {
        $links = resolve(BuildReviewLink::class);
        $business = Business::factory()->create(['tenant_id' => $tenant->id]);

        $tokens = collect(['ChIJdowntown', 'ChIJuptown'])
            ->map(fn (string $placeId): ?ReviewRequest => $links->handle(Location::factory()->create([
                'tenant_id' => $tenant->id,
                'business_id' => $business->id,
                'google_place_id' => $placeId,
            ])))
            ->map(fn (?ReviewRequest $request): ?string => $request?->token);

        expect($tokens->unique())->toHaveCount(2);
    });
});

it('points a token at the branch own review composer', function (): void {
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function () use ($tenant): void {
        $links = resolve(BuildReviewLink::class);
        $request = $links->handle(branch($tenant, 'ChIJ needs+encoding'));

        expect($links->destination($request))
            ->toBe('https://search.google.com/local/writereview?placeid=ChIJ+needs%2Bencoding');
    });
});

it('has no destination once the place id is cleared', function (): void {
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function () use ($tenant): void {
        $links = resolve(BuildReviewLink::class);
        $location = branch($tenant, 'ChIJplace');
        $request = $links->handle($location);

        $location->update(['google_place_id' => null]);

        expect($links->destination($request->refresh()))->toBeNull();
    });
});

it('keeps its token out of the place id', function (): void {
    // A token derived from the place id would leak which profile a card points at and
    // make every tenant's link forgeable from public information.
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function () use ($tenant): void {
        $request = resolve(BuildReviewLink::class)->handle(branch($tenant, 'ChIJplace'));

        expect($request?->token)->toHaveLength(12)
            ->and($request?->token)->not->toContain('ChIJplace');
    });
});
