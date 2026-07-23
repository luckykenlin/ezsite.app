<?php

declare(strict_types=1);

use App\Models\Tenant;

it('renders each hero variant with its own layout', function (string $variant, string $marker): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['variant' => $variant, 'heading' => 'Welcome friends']],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Welcome friends')
        ->assertSee($marker, false);
})->with([
    'centered-minimal' => ['centered-minimal', 'max-w-3xl'],
    'left-text-right-image' => ['left-text-right-image', 'md:grid-cols-2'],
    'full-bleed-overlay' => ['full-bleed-overlay', 'bg-neutral'],
]);

it('escapes hero content to prevent stored XSS', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => '<script>alert(1)</script>']],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;', false);
});
