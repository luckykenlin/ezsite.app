<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\Tenant;

/*
 * A draft page is invisible to the public site.
 *
 * Each tenant here keeps a second PUBLISHED page on purpose, so the site as a
 * whole is live and a draft is a genuine 404. A site with nothing published at
 * all gets a different answer — "coming soon" — which lives in
 * tests/Feature/Http/MissingPageTest.
 */

it('serves a published home page and hides it again once it goes back to draft', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Live home']],
    ]);
    $this->createTenantPage($tenant, [
        ['type' => 'heading', 'data' => ['content' => 'About us']],
    ], 'about');

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Live home');

    $this->runInTenant($tenant, fn (): bool => Page::query()
        ->where('slug', '/')
        ->sole()
        ->update(['status' => PageStatus::Draft]));

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertNotFound()
        ->assertDontSee('Live home');
});

it('returns 404 for a draft subpage resolved by slug', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Live home']],
    ]);
    $this->createTenantPage($tenant, [
        ['type' => 'heading', 'data' => ['content' => 'About us']],
    ], 'about');

    $this->get(sprintf('http://acme.%s/about', $this->centralDomain()))
        ->assertOk()
        ->assertSee('About us');

    $this->runInTenant($tenant, fn (): bool => Page::query()
        ->where('slug', 'about')
        ->sole()
        ->update(['status' => PageStatus::Draft]));

    $this->get(sprintf('http://acme.%s/about', $this->centralDomain()))
        ->assertNotFound()
        ->assertDontSee('About us');
});
