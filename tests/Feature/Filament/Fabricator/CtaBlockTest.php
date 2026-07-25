<?php

declare(strict_types=1);

use App\Models\Tenant;

it('renders each cta variant with its own layout', function (string $variant, string $marker): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [
        ['type' => 'cta', 'data' => [
            'variant' => $variant,
            'heading' => 'Ready to book?',
            'body' => 'Slots fill up fast.',
            'cta_label' => 'Book now',
            'cta_url' => '/contact',
            'secondary_label' => 'Call us',
            'secondary_url' => 'tel:+15551234567',
        ]],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Ready to book?')
        ->assertSee('Book now')
        ->assertSee('Call us')
        ->assertSee($marker, false);
})->with([
    'banner' => ['banner', 'bg-primary text-primary-content'],
    'boxed' => ['boxed', 'card-actions'],
]);

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

it('escapes cta content to prevent stored XSS', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [
        ['type' => 'cta', 'data' => [
            'variant' => 'boxed',
            'heading' => '<script>alert(1)</script>',
            'cta_label' => 'Go',
            'cta_url' => '/x',
        ]],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;', false);
});
