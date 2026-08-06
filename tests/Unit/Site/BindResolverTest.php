<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Location;
use App\Models\Tenant;
use App\Site\BindResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

it('memoizes the business and locations so a full resolution pass costs at most two queries', function (): void {
    $tenant = Tenant::factory()->create();
    $business = $this->createTenantBusiness($tenant, [], 2);
    $locationIds = Location::query()->pluck('id')->all();

    $resolver = new BindResolver;
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $resolver->business();
    $resolver->business();
    $resolver->locations();
    $resolver->location(null);
    $resolver->location($locationIds[1]);

    expect($queries)->toHaveCount(2)
        ->and($resolver->business()->is(Business::query()->findOrFail($business->getKey())))->toBeTrue();
});

it('memoizes the absence of a business without re-querying', function (): void {
    $resolver = new BindResolver;
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    expect($resolver->business())->toBeNull()
        ->and($resolver->business())->toBeNull()
        ->and($queries)->toHaveCount(1);
});

it('resolves a null location id to the primary location', function (): void {
    $tenant = Tenant::factory()->create();
    $business = $this->createTenantBusiness($tenant, [], 0);

    // Insert the primary AFTER a non-primary so ordering, not insertion, decides.
    $this->runInTenant($tenant, function () use ($tenant, $business): void {
        Location::factory()->create(['tenant_id' => $tenant->id, 'business_id' => $business->id, 'is_primary' => false]);
        Location::factory()->create(['tenant_id' => $tenant->id, 'business_id' => $business->id, 'is_primary' => true, 'label' => 'Primary spot']);
    });

    expect((new BindResolver)->location(null)?->label)->toBe('Primary spot');
});

it('resolves an explicit location id to that location', function (): void {
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, [], 2);
    $secondary = Location::query()->where('is_primary', false)->firstOrFail();

    expect((new BindResolver)->location($secondary->id)?->is($secondary))->toBeTrue();
});

it('falls back to the primary location with a warning when the stored id has gone stale', function (): void {
    Log::spy();

    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, [], 1);
    $primary = Location::query()->firstOrFail();

    expect((new BindResolver)->location($primary->id + 999)?->is($primary))->toBeTrue();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => $message === 'fabricator.bind_fallback')
        ->once();
});

it('returns null when the tenant has no locations at all', function (): void {
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, [], 0);

    expect((new BindResolver)->location(null))->toBeNull();
});
