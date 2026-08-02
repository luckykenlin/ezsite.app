<?php

declare(strict_types=1);

use App\Actions\UpdateDesignTokens;
use App\Design\StylePreset;
use App\Models\Business;
use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;

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

it('gives a logoless site its own generated browser-tab icon', function (): void {
    // Before this every customer's site shared one favicon — the package's
    // `favicon.ico` — so tabs open on eight of our sites were indistinguishable,
    // including to the owners looking at their own. A site with no logo yet is
    // the state every site is in the day it is provisioned, so the generated
    // mark is the one that matters most.
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->runInTenant($tenant, fn (): Business => Business::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Jade Nails',
        'brand_primary' => '#336699',
        'logo_path' => null,
        'logo_media_id' => null,
    ]));
    $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);

    $response = $this->get(sprintf('http://acme.%s/', $this->centralDomain()))->assertOk();

    expect(rawurldecode($response->content()))
        ->toContain('rel="icon" type="image/svg+xml"')
        ->toContain('fill="#336699"')
        ->toContain('>J<')
        // Ours comes AFTER the package's raster default, which is the canonical
        // two-line favicon pattern and the reason it is not suppressed.
        ->toMatch('/favicon\.ico.*image\/svg\+xml/s');
});

it('prefers the logo the owner uploaded as the tab icon', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->runInTenant($tenant, fn (): Business => Business::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Jade Nails',
        'logo_path' => 'logos/jade.png',
        'logo_media_id' => null,
    ]));
    $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('rel="icon" href="'.Storage::disk('public')->url('logos/jade.png').'"', false);
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
