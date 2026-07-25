<?php

declare(strict_types=1);

use App\Models\Tenant;

it('renders each testimonials variant with its own layout', function (string $variant, string $marker): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [
        ['type' => 'testimonials', 'data' => [
            'variant' => $variant,
            'heading' => 'What clients say',
            'testimonials' => [
                ['quote' => 'Absolutely wonderful service', 'author' => 'Amy Chen', 'role' => 'Regular', 'avatar_url' => 'https://example.com/amy.jpg'],
                ['quote' => 'Best in town', 'author' => 'Ben Wu'],
            ],
        ]],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('What clients say')
        ->assertSee('Absolutely wonderful service')
        ->assertSee('Amy Chen')
        ->assertSee('Ben Wu')
        ->assertSee($marker, false);
})->with([
    'grid' => ['grid', 'md:grid-cols-2'],
    'carousel' => ['carousel', 'carousel-item'],
]);

it('renders uuid-keyed repeater state and skips malformed items', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [
        ['type' => 'testimonials', 'data' => [
            'variant' => 'grid',
            'testimonials' => [
                'a1b2-uuid' => ['quote' => 'Panel-authored quote', 'author' => 'Cara'],
                'bad-item' => 42,
            ],
        ]],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Panel-authored quote')
        ->assertSee('Cara');
});

it('escapes testimonials content to prevent stored XSS', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [
        ['type' => 'testimonials', 'data' => [
            'variant' => 'grid',
            'testimonials' => [['quote' => '<script>alert(1)</script>', 'author' => 'Eve']],
        ]],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;', false);
});
