<?php

declare(strict_types=1);

use App\Models\Location;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

it('renders each footer variant with the copyright line and brand', function (string $variant, string $marker): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $business = $this->createTenantBusiness($tenant, [
        'name' => 'Corner Cafe',
        'tagline' => 'Best brews in town',
    ], 0);
    $this->runInTenant($tenant, fn (): Location => Location::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'is_primary' => true,
        'address_line1' => '123 Main St',
        'phone' => '+1 555 222 3333',
    ]));
    $this->createTenantPage($tenant, [
        ['type' => 'footer', 'data' => [
            'variant' => $variant,
            'nav_links' => [['label' => 'Privacy', 'url' => '/privacy']],
            'note' => 'Licensed and insured.',
        ]],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee(sprintf('&copy; %d Corner Cafe', now()->year), false)
        ->assertSee('Privacy')
        ->assertSee('Licensed and insured.')
        ->assertSee($marker, false);
})->with([
    'columns' => ['columns', 'sm:footer-horizontal'],
    'minimal' => ['minimal', 'footer-center'],
]);

it('renders the location NAP and business tagline in the columns variant', function (): void {
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
        ->assertSee('Best brews in town')
        ->assertSee('123 Main St')
        ->assertSee('+1 555 222 3333')
        ->assertSee('hq@business.test');
});

it('skips the footer block with a warning when the tenant has no locations', function (): void {
    Log::spy();

    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantBusiness($tenant, ['name' => 'Corner Cafe'], 0);
    $this->createTenantPage($tenant, [
        ['type' => 'footer', 'data' => ['variant' => 'minimal']],
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Still here']],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Still here')
        ->assertDontSee('footer-center');

    // The default footer chrome fails to bind here too (business without
    // locations), so the page's footer block and the chrome's each warn.
    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $message === 'fabricator.block_skipped'
            && $context['reason'] === 'unresolved_bind'
            && $context['type'] === 'footer')
        ->atLeast()->once();
});
