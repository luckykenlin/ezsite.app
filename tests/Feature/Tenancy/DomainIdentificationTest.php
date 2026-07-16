<?php

declare(strict_types=1);

use App\Models\Tenant;

it('resolves a request to the owning tenant', function (string $domain, bool $isCustom): void {
    $tenant = Tenant::factory()->withDomain($domain)->create();
    $this->createTenantHomePage($tenant);

    $host = $isCustom ? $domain : sprintf('%s.%s', $domain, $this->centralDomain());

    $this->get(sprintf('http://%s/', $host))
        ->assertOk()
        ->assertSee((string) $tenant->id);
})->with('tenant_domains');

it('redirects an unknown host to the central home page', function (string $domain, bool $isCustom): void {
    Tenant::factory()->withDomain($domain)->create();

    $host = $isCustom ? 'unknown-domain.test' : sprintf('unknown.%s', $this->centralDomain());

    $this->get(sprintf('http://%s/', $host))
        ->assertRedirect(config()->string('app.url'));
})->with('tenant_domains');
