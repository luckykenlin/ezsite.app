<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\Tenant;

it('serves published pages but returns 404 for drafts on the public site', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Live home']],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Live home');

    $this->runInTenant($tenant, fn (): bool => Page::query()->sole()->update(['status' => PageStatus::Draft]));

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertNotFound();
});

it('returns 404 for a draft subpage resolved by slug', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [
        ['type' => 'heading', 'data' => ['content' => 'About us']],
    ], 'about');

    $this->get(sprintf('http://acme.%s/about', $this->centralDomain()))
        ->assertOk()
        ->assertSee('About us');

    $this->runInTenant($tenant, fn (): bool => Page::query()->sole()->update(['status' => PageStatus::Draft]));

    $this->get(sprintf('http://acme.%s/about', $this->centralDomain()))
        ->assertNotFound();
});
