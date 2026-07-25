<?php

declare(strict_types=1);

use App\Models\SiteSetting;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

it('wraps every page in default chrome as soon as the tenant has a business and a location', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantBusiness($tenant, ['name' => 'Corner Cafe'], 1);
    $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Welcome')
        ->assertSee('navbar-end', false) // default header (simple variant)
        ->assertSee('sm:footer-horizontal', false) // default footer (columns variant)
        ->assertSeeInOrder(['navbar', 'Welcome', 'footer'], false);
});

it('renders the saved chrome configuration instead of the defaults', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantBusiness($tenant, ['name' => 'Corner Cafe'], 1);
    $this->runInTenant($tenant, fn (): SiteSetting => SiteSetting::factory()
        ->withHeader()
        ->withFooter()
        ->create(['tenant_id' => $tenant->id]));
    $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Saved nav link') // saved header (centered variant)
        ->assertDontSee('navbar-end')
        ->assertSee('Saved footer note') // saved footer (minimal variant)
        ->assertDontSee('sm:footer-horizontal');
});

it('renders no chrome and logs nothing for a tenant without a business', function (): void {
    Log::spy();

    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Welcome')
        ->assertDontSee('navbar')
        ->assertDontSee('footer-title');

    Log::shouldNotHaveReceived('warning');
});
