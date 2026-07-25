<?php

declare(strict_types=1);

use App\Models\Tenant;

it('renders each gallery variant with its own layout', function (string $variant, string $marker): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [
        ['type' => 'gallery', 'data' => [
            'variant' => $variant,
            'heading' => 'Our work',
            'images' => [
                ['url' => 'https://example.com/one.jpg', 'alt' => 'First shot', 'caption' => 'Signature look'],
                ['url' => 'https://example.com/two.jpg'],
            ],
        ]],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Our work')
        ->assertSee('https://example.com/one.jpg')
        ->assertSee('Signature look')
        ->assertSee($marker, false);
})->with([
    'grid' => ['grid', 'sm:grid-cols-3'],
    'masonry' => ['masonry', 'columns-2'],
]);

it('renders uuid-keyed repeater state and skips items without a url', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [
        ['type' => 'gallery', 'data' => [
            'variant' => 'grid',
            'images' => [
                'a1b2-uuid' => ['url' => 'https://example.com/panel.jpg'],
                'no-url' => ['caption' => 'Orphan caption'],
            ],
        ]],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('https://example.com/panel.jpg')
        ->assertDontSee('Orphan caption');
});

it('escapes gallery content to prevent stored XSS', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [
        ['type' => 'gallery', 'data' => [
            'variant' => 'grid',
            'images' => [['url' => 'https://example.com/x.jpg', 'caption' => '<script>alert(1)</script>']],
        ]],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;', false);
});
