<?php

declare(strict_types=1);

use App\Models\Tenant;

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
