<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Location;
use App\Models\Tenant;
use Illuminate\Database\QueryException;

test('a tenant can only have one business (unique tenant_id)', function (): void {
    $tenant = Tenant::factory()->create();

    Business::factory()->create(['tenant_id' => $tenant->id, 'name' => 'First Business']);

    expect(fn () => Business::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Second Business']))
        ->toThrow(QueryException::class, 'tenant_id');
});

test('tenant relation returns the owning tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $business = Business::factory()->create(['tenant_id' => $tenant->id]);

    expect($business->tenant->is($tenant))->toBeTrue();
});

test('locations relation returns the business locations', function (): void {
    $business = Business::factory()->create();
    Location::factory()->count(2)->create([
        'tenant_id' => $business->tenant_id,
        'business_id' => $business->id,
    ]);

    expect($business->locations)->toHaveCount(2);
});

test('slug is auto-generated from name via laravel-sluggable', function (): void {
    $business = Business::factory()->create(['name' => 'The Corner Cafe']);

    expect($business->slug)->toBe('the-corner-cafe');
});

test('slug is scoped per tenant so two tenants can share a name and slug', function (): void {
    $businessA = Business::factory()->create(['name' => 'Shared Name']);
    $businessB = Business::factory()->create(['name' => 'Shared Name']);

    expect($businessA->slug)->toBe('shared-name')
        ->and($businessB->slug)->toBe('shared-name');
});

test('soft-deleting a business cascades to its locations and restoring brings them back', function (): void {
    $business = Business::factory()->create();
    Location::factory()->count(2)->create([
        'tenant_id' => $business->tenant_id,
        'business_id' => $business->id,
    ]);

    $business->delete();
    expect(Location::query()->count())->toBe(0)
        ->and(Location::withTrashed()->count())->toBe(2);

    $business->restore();
    expect(Location::query()->count())->toBe(2);
});

test('force-deleting a business skips the soft-delete cascade and removes locations via the DB cascade', function (): void {
    $business = Business::factory()->create();
    Location::factory()->count(2)->create([
        'tenant_id' => $business->tenant_id,
        'business_id' => $business->id,
    ]);

    $business->forceDelete();

    expect(Location::withTrashed()->count())->toBe(0);
});

test('to array', function (): void {
    $business = Business::factory()->create();
    $business = Business::query()->findOrFail($business->getKey());

    expect(array_keys($business->toArray()))
        ->toBe([
            'id',
            'tenant_id',
            'name',
            'slug',
            'category',
            'tagline',
            'description',
            'logo_path',
            'brand_primary',
            'brand_secondary',
            'brand_accent',
            'contact_email',
            'contact_phone',
            'website_url',
            'timezone',
            'locale',
            'currency',
            'status',
            'created_at',
            'updated_at',
            'deleted_at',
        ]);
});
