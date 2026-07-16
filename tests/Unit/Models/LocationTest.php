<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Location;
use App\Models\Tenant;

test('business relation returns the owning business', function (): void {
    $business = Business::factory()->create();
    $location = Location::factory()->create([
        'tenant_id' => $business->tenant_id,
        'business_id' => $business->id,
    ]);

    expect($location->business->is($business))->toBeTrue();
});

test('tenant relation returns the owning tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);

    expect($location->tenant->is($tenant))->toBeTrue();
});

test('to array', function (): void {
    $location = Location::factory()->create();
    $location = Location::query()->findOrFail($location->getKey());

    expect(array_keys($location->toArray()))
        ->toBe([
            'id',
            'tenant_id',
            'business_id',
            'label',
            'is_primary',
            'address_line1',
            'address_line2',
            'city',
            'state',
            'postal_code',
            'country',
            'latitude',
            'longitude',
            'phone',
            'email',
            'timezone',
            'status',
            'created_at',
            'updated_at',
            'deleted_at',
        ]);
});
