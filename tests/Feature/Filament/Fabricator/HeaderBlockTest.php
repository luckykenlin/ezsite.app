<?php

declare(strict_types=1);

use App\Models\Tenant;

it('renders the business logo when one is set', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantBusiness($tenant, ['name' => 'Corner Cafe', 'logo_path' => 'logos/corner.png'], 0);
    $this->createTenantPage($tenant, [
        ['type' => 'header', 'data' => ['variant' => 'simple']],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('logos/corner.png');
});
