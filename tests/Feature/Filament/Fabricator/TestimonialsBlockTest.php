<?php

declare(strict_types=1);

use App\Models\Tenant;

it('renders uuid-keyed repeater state and skips malformed items', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [
        ['type' => 'testimonials', 'data' => [
            'variant' => 'grid',
            'testimonials' => [
                'a1b2-uuid' => ['quote' => 'Panel-authored quote', 'author' => 'Cara'],
                'bad-item' => 'not an array',
            ],
        ]],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Panel-authored quote')
        ->assertSee('Cara')
        ->assertDontSee('not an array');
});
