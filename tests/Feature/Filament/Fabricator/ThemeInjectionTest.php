<?php

declare(strict_types=1);

use App\Actions\UpdateDesignTokens;
use App\Design\StylePreset;
use App\Models\Business;
use App\Models\Tenant;

it('injects the tenant theme variables into the public site head', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->runInTenant($tenant, fn (): Business => Business::factory()
        ->themed(StylePreset::WarmCraft)
        ->create(['tenant_id' => $tenant->id]));
    $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('data-site-theme', false)
        ->assertSee('--color-primary: oklch(55% 0.12 40);', false) // WarmSand
        ->assertSee('--radius-box: 1rem;', false)
        ->assertSee("--font-heading: 'Playfair Display'", false);
});

it('emits the brand hex colors when the palette is brand', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $business = $this->runInTenant($tenant, fn (): Business => Business::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_primary' => '#336699',
    ]));
    $this->runInTenant($tenant, fn (): Business => resolve(UpdateDesignTokens::class)
        ->handle($business, ['palette' => 'brand']));
    $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('--color-primary: #336699;', false);
});

it('renders the stock theme with no style tag for a tenant without a business', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Welcome')
        ->assertDontSee('data-site-theme');
});
