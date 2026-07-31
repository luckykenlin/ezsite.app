<?php

declare(strict_types=1);

use App\Models\Media;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromUnwantedDomains;

/**
 * Media references in stored blocks: id → URL translation on the live
 * render path, batching, dangling-reference defenses, and the tenant
 * middleware patch on Curator's Glide route.
 */
it('renders media-backed blocks by translating ids into the url props', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $media = $this->runInTenant($tenant, fn (): Media => Media::factory()->create([
        'tenant_id' => $tenant->id,
        'path' => 'media/storefront.jpg',
    ]));

    $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['variant' => 'left-text-right-image', 'heading' => 'Welcome', 'image_id' => $media->id]],
        ['type' => 'gallery', 'data' => ['heading' => 'Work', 'images' => [
            ['media_id' => $media->id, 'alt' => 'From the library'],
            ['url' => 'https://example.com/external.jpg', 'alt' => 'External'],
        ]]],
        // A feature item's own photo. Nothing was added to the resolver for
        // this: resolveMediaUrls() already descends into list values and
        // injects per item, which is how gallery images work — so the same
        // `image_id => image_url` mapping the hero uses at the top level works
        // one level down without a new MEDIA_KEYS entry.
        ['type' => 'features', 'data' => ['variant' => 'grid', 'features' => [
            ['title' => 'Deep clean', 'image_id' => $media->id],
        ]]],
        ['type' => 'offerings', 'data' => ['variant' => 'cards', 'items' => [
            ['name' => 'Half day', 'price' => 'from $400', 'image_id' => $media->id],
        ]]],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('media/storefront.jpg')
        ->assertSee('https://example.com/external.jpg')
        ->assertSee('From the library');
});

it('resolves the media reference over a stale stored url string', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $media = $this->runInTenant($tenant, fn (): Media => Media::factory()->create([
        'tenant_id' => $tenant->id,
        'path' => 'media/fresh.jpg',
    ]));

    $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['variant' => 'left-text-right-image', 'heading' => 'Welcome',
            'image_id' => $media->id, 'image_url' => 'https://example.com/stale.jpg']],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('media/fresh.jpg')
        ->assertDontSee('stale.jpg');
});

it('degrades a dangling media reference to the block empty-image guard', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();

    $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['variant' => 'left-text-right-image', 'heading' => 'Still here', 'image_id' => 999999]],
        ['type' => 'gallery', 'data' => ['heading' => 'Work', 'images' => [
            ['media_id' => 999999, 'alt' => 'Gone'],
        ]]],
    ]);

    $response = $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Still here');

    // No <img> should render for either dangling reference.
    expect(mb_substr_count((string) $response->getContent(), '<img'))->toBe(0);
});

it('loads all media references for a page in one query', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    [$first, $second] = $this->runInTenant(
        $tenant,
        fn () => Media::factory()->count(2)->create(['tenant_id' => $tenant->id])->all(),
    );

    $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['variant' => 'left-text-right-image', 'heading' => 'Welcome', 'image_id' => $first->id]],
        ['type' => 'gallery', 'data' => ['heading' => 'Work', 'images' => [
            ['media_id' => $second->id, 'alt' => 'A'],
            ['media_id' => $first->id, 'alt' => 'B'],
        ]]],
    ]);

    $queries = 0;
    DB::listen(function ($query) use (&$queries): void {
        if (str_contains((string) $query->sql, 'curator')) {
            $queries++;
        }
    });

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))->assertOk();

    expect($queries)->toBe(1);
});

it('runs the curator glide route inside tenant context', function (): void {
    $middleware = collect(Route::getRoutes()->getRoutes())
        ->first(fn ($route): bool => str_contains($route->uri(), 'curator/'))
        ?->middleware();

    expect($middleware)->toContain(InitializeTenancyByDomainOrSubdomain::class)
        ->and($middleware)->toContain(PreventAccessFromUnwantedDomains::class);
});
