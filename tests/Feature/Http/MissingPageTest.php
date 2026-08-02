<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Models\Business;
use App\Models\Page;
use App\Models\Tenant;
use App\Models\User;

/*
 * What a domain in this installation says when the page asked for is not there.
 *
 * Three answers, and which one you get is the whole behaviour: a tenant with
 * nothing published is unfinished, not broken, and says "coming soon"; a live
 * tenant site says "not found" in its own colours; the central domain says it in
 * the marketing frame. Before this, all three were Laravel's grey default page —
 * which on a customer's own domain reads as "this business's website is broken".
 */

function liveTenant(string $subdomain = 'acme'): Tenant
{
    $tenant = Tenant::factory()->withDomain($subdomain)->create();

    test()->runInTenant($tenant, fn (): Business => Business::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Jade Nails',
    ]));

    test()->createTenantPage($tenant, [
        ['type' => 'heading', 'data' => ['content' => 'Welcome', 'level' => 'h2']],
    ]);

    return $tenant;
}

it('says coming soon while a brand-new site has nothing published', function (string $path): void {
    // The state ProvisionSiteFromTemplate leaves behind: pages exist, all Draft.
    // A bare 404 here lands on whoever the owner sent the link to on day one.
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->runInTenant($tenant, fn (): Business => Business::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Jade Nails',
    ]));
    $page = $this->createTenantPage($tenant, []);
    $this->runInTenant($tenant, fn (): bool => $page->update(['status' => PageStatus::Draft]));

    $this->get(sprintf('http://acme.%s%s', $this->centralDomain(), $path))
        ->assertOk()
        ->assertSee('Coming soon')
        ->assertSee('Jade Nails')
        // A 200 keeps the owner's own "is it working?" check honest; the meta tag
        // is what keeps the placeholder out of the index.
        ->assertSee('name="robots" content="noindex"', false);
})->with([
    'the home page' => ['/'],
    'a page that was never created' => ['/specials'],
]);

it('says not found in the site own colours once it is live', function (string $path): void {
    liveTenant();

    $this->get(sprintf('http://acme.%s%s', $this->centralDomain(), $path))
        ->assertNotFound()
        ->assertSee('We could not find that page')
        ->assertSee('Jade Nails')
        ->assertSee('Back to the home page')
        // Themed like the site it belongs to, not like a framework.
        ->assertSee('data-site-theme', false);
})->with([
    'an unknown slug' => ['/specials'],
    'a nested unknown slug' => ['/services/manicure'],
]);

it('still refuses a draft page on a site that is otherwise live', function (): void {
    // The coming-soon answer is about the SITE having nothing published, not
    // about this page: a live site's unpublished draft is a 404, as before.
    $tenant = liveTenant();

    $draft = $this->runInTenant($tenant, fn (): Page => Page::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Specials',
        'slug' => 'specials',
        'layout' => 'main',
        'blocks' => [['type' => 'heading', 'data' => ['content' => 'Unreleased', 'level' => 'h2']]],
        'status' => PageStatus::Draft,
    ]));

    $this->get(sprintf('http://acme.%s/specials', $this->centralDomain()))
        ->assertNotFound()
        ->assertDontSee('Unreleased')
        ->assertSee('We could not find that page');

    expect($draft->isDraft())->toBeTrue();
});

it('serves the marketing frame for a miss on the central domain', function (): void {
    $this->get(sprintf('http://%s/nowhere', $this->centralDomain()))
        ->assertNotFound()
        ->assertSee('Page not found')
        ->assertSee('Browse templates');
});

it('leaves the panel and the internal routes failing like routes', function (string $path): void {
    // The coming-soon page must not answer for the panel's own misses or for the
    // editor's internal endpoints, whose callers expect a status, not a page.
    $tenant = Tenant::factory()->withDomain('acme')->create();

    $this->actingAs(User::factory()->memberOf($tenant)->create())
        ->get(sprintf('http://acme.%s%s', $this->centralDomain(), $path))
        ->assertNotFound()
        ->assertDontSee('Coming soon');
})->with([
    'a panel route that does not exist' => ['/admin/nowhere'],
    'an internal editor route' => ['/_editor/preview'],
]);
