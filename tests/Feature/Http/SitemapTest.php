<?php

declare(strict_types=1);

use App\Models\Page;
use App\Models\Tenant;

it('lists the published indexable pages of the requested tenant only', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $other = Tenant::factory()->withDomain('other')->create();

    $this->createTenantPage($tenant, [['type' => 'heading', 'data' => ['content' => 'Hi']]]);
    $this->createTenantPage($tenant, [['type' => 'heading', 'data' => ['content' => 'About']]], 'about');
    $hidden = $this->createTenantPage($tenant, [['type' => 'heading', 'data' => ['content' => 'Secret']]], 'secret');
    $draft = $this->createTenantPage($tenant, [['type' => 'heading', 'data' => ['content' => 'Draft']]], 'draft-page');
    $this->createTenantPage($other, [['type' => 'heading', 'data' => ['content' => 'Nope']]], 'competitor');

    $this->runInTenant($tenant, function () use ($hidden, $draft): void {
        Page::query()->whereKey($hidden->getKey())->update(['is_indexable' => false]);
        Page::query()->whereKey($draft->getKey())->update(['status' => 'draft']);
    });

    $response = $this->get(sprintf('http://acme.%s/sitemap.xml', $this->centralDomain()))->assertOk();

    $response
        ->assertHeader('Content-Type', 'application/xml')
        ->assertSee('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', false)
        ->assertSee(sprintf('<loc>http://acme.%s</loc>', $this->centralDomain()), false)
        ->assertSee(sprintf('<loc>http://acme.%s/about</loc>', $this->centralDomain()), false)
        ->assertDontSee('secret')
        ->assertDontSee('draft-page')
        ->assertDontSee('competitor');
});
