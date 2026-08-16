<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Post;
use App\Models\Tenant;

/*
 * The `updates` block: the one block that stores no content of its own and queries
 * the feed instead, so publishing an update makes it appear on the home page with
 * nobody editing a page.
 *
 * Both of its render guards are tested here, and both are growth requirements
 * rather than polish — see the note in the cards view.
 */

function tenantShowingUpdates(string $variant = 'cards'): Tenant
{
    $tenant = Tenant::factory()->withDomain('acme')->create();

    test()->runInTenant($tenant, fn (): Business => Business::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Jade Nails',
    ]));

    test()->createTenantPage($tenant, [
        ['type' => 'updates', 'data' => ['variant' => $variant, 'heading' => "What's new", 'count' => 3]],
    ], slug: '/');

    return $tenant;
}

it('renders published updates in both variants', function (string $variant): void {
    $tenant = tenantShowingUpdates($variant);

    $this->runInTenant($tenant, function () use ($tenant): void {
        Post::factory()->published()->create(['tenant_id' => $tenant->id, 'title' => 'Spring gel sets']);
        Post::factory()->published()->create(['tenant_id' => $tenant->id, 'title' => 'New opening hours']);
    });

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee("What's new")
        ->assertSee('Spring gel sets')
        ->assertSee('New opening hours')
        // Linked, because the whole point of the section is the permalink.
        ->assertSee('/updates/spring-gel-sets', false);
})->with(['cards', 'list']);

it('renders nothing at all when nothing is published', function (): void {
    $tenant = tenantShowingUpdates();

    $this->runInTenant($tenant, fn (): Post => Post::factory()->create(['tenant_id' => $tenant->id, 'title' => 'Still a draft']));

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertDontSee("What's new")
        ->assertDontSee('Still a draft');
});

it('hides itself once the newest update has gone stale', function (): void {
    // THE guard. A "Latest updates" strip whose freshest item is dated 14 January,
    // still showing every day of September, tells the person comparing three salons
    // that this business may have closed — so the section makes the site convert
    // WORSE than not having it. The operator will not remember to remove it.
    $tenant = tenantShowingUpdates();

    $this->runInTenant($tenant, fn (): Post => Post::factory()->stale()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Ancient news',
    ]));

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertDontSee("What's new")
        ->assertDontSee('Ancient news');
});

it('shows a stale update again if the staleness window is widened', function (): void {
    // Proves the guard reads config rather than hard-coding a quarter.
    config()->set('updates.stale_after_days', 3650);

    $tenant = tenantShowingUpdates();

    $this->runInTenant($tenant, fn (): Post => Post::factory()->stale()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Ancient news',
    ]));

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Ancient news');
});

it('drops a finished offer out of the section while its own page stays up', function (): void {
    $tenant = tenantShowingUpdates();

    $this->runInTenant($tenant, function () use ($tenant): void {
        Post::factory()->published()->create(['tenant_id' => $tenant->id, 'title' => 'Still current']);
        Post::factory()->expired()->create(['tenant_id' => $tenant->id, 'title' => 'Finished offer', 'slug' => 'finished']);
    });

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Still current')
        ->assertDontSee('Finished offer');
});

it('honours a kind filter', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->runInTenant($tenant, fn (): Business => Business::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Jade Nails']));
    $this->createTenantPage($tenant, [
        ['type' => 'updates', 'data' => ['variant' => 'cards', 'heading' => 'Offers', 'count' => 3, 'kind_filter' => 'offer']],
    ], slug: '/');

    $this->runInTenant($tenant, function () use ($tenant): void {
        Post::factory()->offer()->create(['tenant_id' => $tenant->id, 'title' => 'Ten percent off']);
        Post::factory()->published()->create(['tenant_id' => $tenant->id, 'title' => 'Ordinary news']);
    });

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Ten percent off')
        ->assertDontSee('Ordinary news');
});

it('treats an unreadable kind filter as everything', function (): void {
    // Stored tenant data, so tryFrom: garbage means "show everything" rather than an
    // empty section, which is the fail-safe direction.
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->runInTenant($tenant, fn (): Business => Business::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Jade Nails']));
    $this->createTenantPage($tenant, [
        ['type' => 'updates', 'data' => ['variant' => 'cards', 'heading' => "What's new", 'count' => 3, 'kind_filter' => 'nonsense']],
    ], slug: '/');

    $this->runInTenant($tenant, fn (): Post => Post::factory()->published()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Ordinary news',
    ]));

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Ordinary news');
});
