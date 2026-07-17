<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Location;
use App\Models\Tenant;
use Spatie\OpeningHours\OpeningHours;

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

test('opening_hours casts to an OpeningHours value object', function (): void {
    $location = Location::factory()->create([
        'timezone' => 'America/New_York',
        'opening_hours' => OpeningHours::create([
            'monday' => ['09:00-12:00', '13:00-17:00'],
            'tuesday' => ['09:00-17:00'],
            'exceptions' => ['2026-12-25' => []],
        ]),
    ]);

    $location = Location::query()->findOrFail($location->getKey());

    expect($location->opening_hours)->toBeInstanceOf(OpeningHours::class)
        ->and($location->opening_hours->isOpenAt(new DateTimeImmutable('2026-07-13 10:00', new DateTimeZone('America/New_York'))))->toBeTrue()
        ->and($location->opening_hours->isOpenAt(new DateTimeImmutable('2026-07-13 12:30', new DateTimeZone('America/New_York'))))->toBeFalse()
        ->and($location->opening_hours->isOpenAt(new DateTimeImmutable('2026-12-25 10:00', new DateTimeZone('America/New_York'))))->toBeFalse();
});

test('opening_hours is null when not set', function (): void {
    $location = Location::factory()->withoutOpeningHours()->create();

    $location = Location::query()->findOrFail($location->getKey());

    expect($location->opening_hours)->toBeNull();
});

test('opening_hours accepts an OpeningHours instance and round-trips', function (): void {
    $location = Location::factory()->create();

    $location->opening_hours = OpeningHours::create([
        'monday' => ['08:00-16:00'],
    ]);
    $location->save();

    $location = Location::query()->findOrFail($location->getKey());

    expect($location->opening_hours->forDay('monday')->count())->toBe(1)
        ->and($location->opening_hours->isOpenOn('sunday'))->toBeFalse();
});

test('opening_hours rejects a non-array, non-object value', function (): void {
    $location = Location::factory()->make();

    $location->opening_hours = 'closed';
})->throws(InvalidArgumentException::class);

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
            'opening_hours',
            'status',
            'created_at',
            'updated_at',
            'deleted_at',
        ]);
});
