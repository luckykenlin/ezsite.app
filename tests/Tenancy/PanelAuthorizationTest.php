<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;

beforeEach(function (): void {
    $this->centralDomain = array_first(config('tenancy.identification.central_domains'));
});

test('a user with no tenant membership is forbidden from a tenant panel', function (): void {
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => 'acme']);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(sprintf('http://acme.%s/admin', $this->centralDomain));

    $response->assertForbidden();
});

test('a user attached to a tenant can access that tenant panel but not another', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantA->domains()->create(['domain' => 'acme']);

    $tenantB = Tenant::factory()->create();
    $tenantB->domains()->create(['domain' => 'globex']);

    $user = User::factory()->create();
    $user->tenants()->attach($tenantA);

    $this->actingAs($user)
        ->get(sprintf('http://acme.%s/admin', $this->centralDomain))
        ->assertOk();

    $this->actingAs($user)
        ->get(sprintf('http://globex.%s/admin', $this->centralDomain))
        ->assertForbidden();
});

test('a super admin can access any tenant panel and the central panel', function (): void {
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => 'acme']);

    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->get(sprintf('http://acme.%s/admin', $this->centralDomain))
        ->assertOk();

    $this->actingAs($superAdmin)
        ->get(sprintf('http://%s/admin', $this->centralDomain))
        ->assertOk();
});

test('a non-super-admin user is forbidden from the central panel even with tenant memberships', function (): void {
    $tenant = Tenant::factory()->create();

    $user = User::factory()->create();
    $user->tenants()->attach($tenant);

    $response = $this->actingAs($user)->get(sprintf('http://%s/admin', $this->centralDomain));

    $response->assertForbidden();
});
