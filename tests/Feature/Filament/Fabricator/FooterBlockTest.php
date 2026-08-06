<?php

declare(strict_types=1);

use App\Models\Location;
use App\Models\Tenant;

it('renders the copyright line, location NAP and business tagline in the columns variant', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $business = $this->createTenantBusiness($tenant, [
        'name' => 'Corner Cafe',
        'tagline' => 'Best brews in town',
        'contact_email' => 'hq@business.test',
    ], 0);
    $this->runInTenant($tenant, fn (): Location => Location::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'is_primary' => true,
        'address_line1' => '123 Main St',
        'phone' => '+1 555 222 3333',
        'email' => null,
    ]));
    $this->createTenantPage($tenant, [
        ['type' => 'footer', 'data' => ['variant' => 'columns']],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee(sprintf('&copy; %d Corner Cafe', now()->year), false)
        ->assertSee('Best brews in town')
        ->assertSee('123 Main St')
        ->assertSee('+1 555 222 3333')
        ->assertSee('hq@business.test');
});
