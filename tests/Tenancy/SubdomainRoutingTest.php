<?php

declare(strict_types=1);

use App\Models\Tenant;

beforeEach(function (): void {
    $this->centralDomain = array_first(config('tenancy.identification.central_domains'));
});

test('a request to a tenant subdomain resolves to that tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => 'acme']);

    $response = $this->get(sprintf('http://acme.%s/', $this->centralDomain));

    $response->assertOk();
    $response->assertSee((string) $tenant->id);
});

test('a request to an unknown subdomain redirects to the central home page', function (): void {
    Tenant::factory()->create()->domains()->create(['domain' => 'acme']);

    $response = $this->get(sprintf('http://unknown.%s/', $this->centralDomain));

    $response->assertRedirect(config()->string('app.url'));
});

test("a request to a tenant's custom domain resolves to that tenant", function (): void {
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => 'acme-inc.test']);

    $response = $this->get('http://acme-inc.test/');

    $response->assertOk();
    $response->assertSee((string) $tenant->id);
});

test('a request to an unknown custom domain redirects to the central home page', function (): void {
    Tenant::factory()->create()->domains()->create(['domain' => 'acme-inc.test']);

    $response = $this->get('http://unknown-domain.test/');

    $response->assertRedirect(config()->string('app.url'));
});
