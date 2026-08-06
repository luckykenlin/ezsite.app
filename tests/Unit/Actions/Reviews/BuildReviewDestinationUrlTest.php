<?php

declare(strict_types=1);

use App\Actions\Reviews\BuildReviewDestinationUrl;
use App\Actions\Reviews\BuildReviewLink;
use App\Models\Business;
use App\Models\Location;
use App\Models\Tenant;

/**
 * A branch with, or without, a Google place id.
 */
function destinationBranch(Tenant $tenant, ?string $placeId): Location
{
    $business = Business::factory()->create(['tenant_id' => $tenant->id]);

    return Location::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'is_primary' => true,
        'google_place_id' => $placeId,
    ]);
}

it('points a token at the branch own review composer', function (): void {
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function () use ($tenant): void {
        $request = resolve(BuildReviewLink::class)->handle(destinationBranch($tenant, 'ChIJ needs+encoding'));

        expect(resolve(BuildReviewDestinationUrl::class)->handle($request))
            ->toBe('https://search.google.com/local/writereview?placeid=ChIJ+needs%2Bencoding');
    });
});

it('has no destination once the place id is cleared', function (): void {
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function () use ($tenant): void {
        $location = destinationBranch($tenant, 'ChIJplace');
        $request = resolve(BuildReviewLink::class)->handle($location);

        $location->update(['google_place_id' => null]);

        expect(resolve(BuildReviewDestinationUrl::class)->handle($request->refresh()))->toBeNull();
    });
});
