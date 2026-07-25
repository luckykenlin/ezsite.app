<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

it('renders each header variant with the business brand and nav links', function (string $variant, string $marker): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantBusiness($tenant, ['name' => 'Corner Cafe', 'logo_path' => null], 0);
    $this->createTenantPage($tenant, [
        ['type' => 'header', 'data' => [
            'variant' => $variant,
            'nav_links' => [
                ['label' => 'About', 'url' => '/about'],
                ['label' => 'Menu', 'url' => '/menu'],
            ],
            'cta_label' => 'Book a table',
            'cta_url' => '/contact',
        ]],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Corner Cafe')
        ->assertSee('About')
        ->assertSee('Menu')
        ->assertSee('Book a table')
        ->assertSee($marker, false);
})->with([
    'simple' => ['simple', 'navbar-end'],
    'centered' => ['centered', 'flex-col items-center'],
]);

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

it('skips the header block with a warning when the tenant has no business', function (): void {
    Log::spy();

    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [
        ['type' => 'header', 'data' => ['variant' => 'simple']],
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Still here']],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Still here')
        ->assertDontSee('navbar-end');

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $message === 'fabricator.block_skipped'
            && $context['reason'] === 'unresolved_bind'
            && $context['type'] === 'header')
        ->once();
});
