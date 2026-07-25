<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;

test('business relation returns the tenant business', function (): void {
    $tenant = Tenant::factory()->create();
    $business = $this->runInTenant($tenant, fn (): Business => Business::factory()->create(['tenant_id' => $tenant->id]));

    // Re-fetch so both sides live on the central connection — is() compares
    // connection names, and runInTenant leaves the created instance on the
    // tenant connection.
    $business = Business::query()->findOrFail($business->getKey());

    expect(Tenant::query()->findOrFail($tenant->getKey())->business->is($business))->toBeTrue();
});

test('users relation returns attached users', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $tenant->users()->attach($user);

    expect($tenant->users()->whereKey($user->id)->exists())->toBeTrue();
});

test("a tenant's domain accessor returns its oldest domain", function (): void {
    // With subdomain identification, the domain column stores just the
    // subdomain fragment (e.g. "acme"), not the full hostname.
    $tenant = Tenant::factory()->create();
    $oldest = $tenant->domains()->create(['domain' => 'acme', 'created_at' => now()->subMinute()]);
    $tenant->domains()->create(['domain' => 'acme-alt']);

    expect($oldest)->toBeInstanceOf(Domain::class)
        ->and($oldest->tenant_id)->toBe($tenant->id)
        ->and($tenant->domain->is($oldest))->toBeTrue();
});

test('to array', function (): void {
    $tenant = Tenant::factory()->create();
    $tenant = Tenant::query()->findOrFail($tenant->getKey());

    expect(array_keys($tenant->toArray()))
        ->toBe([
            'id',
            'name',
            'email',
            'created_at',
            'updated_at',
            'data',
        ]);
});
