<?php

declare(strict_types=1);

use App\Models\Tenant;

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
