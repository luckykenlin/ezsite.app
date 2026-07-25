<?php

declare(strict_types=1);

use App\Models\Tenant;

it('renders each features variant with its own layout', function (string $variant, string $marker): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [
        ['type' => 'features', 'data' => [
            'variant' => $variant,
            'heading' => 'Why choose us',
            'intro' => 'Three reasons that matter.',
            'features' => [
                ['icon' => '⭐', 'title' => 'Fast turnaround', 'description' => 'Same-day service'],
                ['icon' => '🏆', 'title' => 'Award winning', 'description' => 'Voted #1 locally'],
            ],
        ]],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Why choose us')
        ->assertSee('Fast turnaround')
        ->assertSee('Award winning')
        ->assertSee($marker, false);
})->with([
    'grid' => ['grid', 'lg:grid-cols-3'],
    'list' => ['list', 'divide-y'],
]);

it('renders uuid-keyed repeater state and skips malformed items', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [
        ['type' => 'features', 'data' => [
            'variant' => 'grid',
            'features' => [
                'a1b2-uuid' => ['title' => 'From the panel'],
                'bad-item' => 'not an array',
            ],
        ]],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('From the panel')
        ->assertDontSee('not an array');
});

it('escapes features content to prevent stored XSS', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [
        ['type' => 'features', 'data' => [
            'variant' => 'list',
            'features' => [['title' => '<script>alert(1)</script>']],
        ]],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;', false);
});
