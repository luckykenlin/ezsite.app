<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Location;
use App\Models\Tenant;
use Spatie\OpeningHours\OpeningHours;

test('business relation returns the owning business', function (): void {
    $tenant = Tenant::factory()->create();
    [$businessId, $locationId] = $this->runInTenant($tenant, function () use ($tenant): array {
        $business = Business::factory()->create(['tenant_id' => $tenant->id]);
        $location = Location::factory()->create(['tenant_id' => $tenant->id, 'business_id' => $business->id]);

        return [$business->getKey(), $location->getKey()];
    });

    // Re-fetch in central context: models created inside RunInTenant carry the
    // purged-on-revert `tenant` connection, so read the relation after re-fetch.
    $location = Location::query()->findOrFail($locationId);

    expect($location->business->getKey())->toBe($businessId);
});

test('tenant relation returns the owning tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $location = $this->runInTenant($tenant, fn (): Location => Location::factory()->create(['tenant_id' => $tenant->id]));

    expect($location->tenant->is($tenant))->toBeTrue();
});

test('opening_hours casts to an OpeningHours value object', function (): void {
    $tenant = Tenant::factory()->create();
    $location = $this->runInTenant($tenant, fn (): Location => Location::factory()->create([
        'tenant_id' => $tenant->id,
        'timezone' => 'America/New_York',
        'opening_hours' => OpeningHours::create([
            'monday' => ['09:00-12:00', '13:00-17:00'],
            'tuesday' => ['09:00-17:00'],
            'exceptions' => ['2026-12-25' => []],
        ]),
    ]));

    $location = Location::query()->findOrFail($location->getKey());

    expect($location->opening_hours)->toBeInstanceOf(OpeningHours::class)
        ->and($location->opening_hours->isOpenAt(new DateTimeImmutable('2026-07-13 10:00', new DateTimeZone('America/New_York'))))->toBeTrue()
        ->and($location->opening_hours->isOpenAt(new DateTimeImmutable('2026-07-13 12:30', new DateTimeZone('America/New_York'))))->toBeFalse()
        ->and($location->opening_hours->isOpenAt(new DateTimeImmutable('2026-12-25 10:00', new DateTimeZone('America/New_York'))))->toBeFalse();
});

test('opening_hours is null when not set', function (): void {
    $tenant = Tenant::factory()->create();
    $location = $this->runInTenant($tenant, fn (): Location => Location::factory()->withoutOpeningHours()->create(['tenant_id' => $tenant->id]));

    $location = Location::query()->findOrFail($location->getKey());

    expect($location->opening_hours)->toBeNull();
});

test('opening_hours accepts an OpeningHours instance and round-trips', function (): void {
    $tenant = Tenant::factory()->create();
    $location = $this->runInTenant($tenant, function () use ($tenant): Location {
        $location = Location::factory()->create(['tenant_id' => $tenant->id]);

        $location->opening_hours = OpeningHours::create([
            'monday' => ['08:00-16:00'],
        ]);
        $location->save();

        return $location;
    });

    $location = Location::query()->findOrFail($location->getKey());

    expect($location->opening_hours->forDay('monday')->count())->toBe(1)
        ->and($location->opening_hours->isOpenOn('sunday'))->toBeFalse();
});

test('opening_hours rejects a non-array, non-object value', function (): void {
    // Explicit tenant_id/business_id so the factory doesn't eagerly persist a
    // Business (its default is a create() closure) — make() alone stays unpersisted.
    $location = Location::factory()->make(['tenant_id' => 'x', 'business_id' => 1]);

    $location->opening_hours = 'closed';
})->throws(InvalidArgumentException::class);

test('to array', function (): void {
    $tenant = Tenant::factory()->create();
    $location = $this->runInTenant($tenant, fn (): Location => Location::factory()->create(['tenant_id' => $tenant->id]));
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
            'opening_hours',
            'status',
            'created_at',
            'updated_at',
            'deleted_at',
        ]);
});
