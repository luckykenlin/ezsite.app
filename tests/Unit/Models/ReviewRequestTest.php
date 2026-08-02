<?php

declare(strict_types=1);

use App\Enums\ReviewRequestStatus;
use App\Models\Business;
use App\Models\Location;
use App\Models\ReviewRequest;
use App\Models\Tenant;
use Illuminate\Database\QueryException;

test('tenant relation returns the owning tenant', function (): void {
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function () use ($tenant): void {
        $request = ReviewRequest::factory()->create(['tenant_id' => $tenant->id]);

        expect($request->tenant->is($tenant))->toBeTrue();
    });
});

test('business relation returns the business being reviewed', function (): void {
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function () use ($tenant): void {
        $business = Business::factory()->create(['tenant_id' => $tenant->id]);
        $request = ReviewRequest::factory()->create(['tenant_id' => $tenant->id, 'business_id' => $business->id]);

        expect($request->business->is($business))->toBeTrue();
    });
});

test('location relation returns the branch the review is for', function (): void {
    // Google reviews are per-LOCATION, which is why a link belongs to one.
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function () use ($tenant): void {
        $business = Business::factory()->create(['tenant_id' => $tenant->id]);
        $location = Location::factory()->create(['tenant_id' => $tenant->id, 'business_id' => $business->id]);
        $request = ReviewRequest::factory()->create([
            'tenant_id' => $tenant->id,
            'business_id' => $business->id,
            'location_id' => $location->id,
        ]);

        expect($request->location->is($location))->toBeTrue();
    });
});

test('the tracked path is short enough to read off a counter card', function (): void {
    $tenant = Tenant::factory()->create();

    $request = $this->runInTenant($tenant, fn (): ReviewRequest => ReviewRequest::factory()->create([
        'tenant_id' => $tenant->id,
        'token' => 'abc123abc123',
    ]));

    expect($request->getUrl())->toBe('/r/abc123abc123')
        ->and(ReviewRequest::PATH_PREFIX)->toBe('r');
});

test('a new ask starts queued with nothing stamped', function (): void {
    $tenant = Tenant::factory()->create();

    $request = $this->runInTenant($tenant, fn (): ReviewRequest => ReviewRequest::factory()->create([
        'tenant_id' => $tenant->id,
    ]));

    expect($request->status)->toBe(ReviewRequestStatus::Queued)
        ->and($request->clicked_at)->toBeNull()
        ->and($request->sent_at)->toBeNull();
});

test('a token cannot be reused across the installation', function (): void {
    // Unique globally rather than per tenant: /r/{token} resolves before anything
    // else, and a collision would hand one tenant's customer to another's profile.
    $first = Tenant::factory()->create();
    $second = Tenant::factory()->create();

    $this->runInTenant($first, fn (): ReviewRequest => ReviewRequest::factory()->create([
        'tenant_id' => $first->id,
        'token' => 'sharedtoken1',
    ]));

    $this->runInTenant($second, function () use ($second): void {
        expect(fn (): ReviewRequest => ReviewRequest::factory()->create([
            'tenant_id' => $second->id,
            'token' => 'sharedtoken1',
        ]))->toThrow(QueryException::class);
    });
});

test('to array', function (): void {
    $tenant = Tenant::factory()->create();
    $request = $this->runInTenant($tenant, fn (): ReviewRequest => ReviewRequest::factory()->create([
        'tenant_id' => $tenant->id,
    ]));
    $request = ReviewRequest::query()->findOrFail($request->getKey());

    expect(array_keys($request->toArray()))
        ->toBe([
            'id',
            'tenant_id',
            'business_id',
            'location_id',
            'channel',
            'recipient',
            'status',
            'token',
            'sent_at',
            'clicked_at',
            'created_at',
            'updated_at',
        ]);
});
