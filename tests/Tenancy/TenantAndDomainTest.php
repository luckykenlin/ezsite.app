<?php

declare(strict_types=1);

use App\Models\Domain;
use App\Models\Tenant;

test('the custom tenant and domain models are bound in config', function (): void {
    expect(config('tenancy.models.tenant'))->toBe(Tenant::class)
        ->and(config('tenancy.models.domain'))->toBe(Domain::class);
});

test('a tenant can be created and given a domain', function (): void {
    // With subdomain identification, the domain column stores just the
    // subdomain fragment (e.g. "acme"), not the full hostname.
    $tenant = Tenant::factory()->create();
    $domain = $tenant->domains()->create(['domain' => 'acme']);

    expect($domain)->toBeInstanceOf(Domain::class)
        ->and($domain->tenant_id)->toBe($tenant->id);

    $this->assertDatabaseHas('tenants', ['id' => $tenant->id]);
    $this->assertDatabaseHas('domains', [
        'domain' => 'acme',
        'tenant_id' => $tenant->id,
    ]);
});

test('a tenant can have multiple domains', function (): void {
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => 'acme']);
    $tenant->domains()->create(['domain' => 'acme-alt']);

    expect($tenant->domains()->count())->toBe(2);
});

test("a tenant's domain accessor returns its oldest domain", function (): void {
    $tenant = Tenant::factory()->create();
    $oldest = $tenant->domains()->create(['domain' => 'acme', 'created_at' => now()->subMinute()]);
    $tenant->domains()->create(['domain' => 'acme-alt']);

    expect($tenant->domain->is($oldest))->toBeTrue();
});
