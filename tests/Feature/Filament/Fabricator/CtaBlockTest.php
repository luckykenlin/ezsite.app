<?php

declare(strict_types=1);

use App\Models\Tenant;

it('omits link buttons when label or url is missing', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [
        ['type' => 'cta', 'data' => [
            'variant' => 'banner',
            'heading' => 'Just a headline',
            'cta_label' => 'Label without url',
        ]],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Just a headline')
        ->assertDontSee('Label without url');
});
