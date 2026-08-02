<?php

declare(strict_types=1);

use App\Filament\Fabricator\BlockRegistry;
use App\Models\Media;
use App\Models\Tenant;
use App\Site\Blocks\BlockType;
use App\Site\Blocks\BlockVocabulary;
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
        ['type' => 'offerings', 'data' => ['items' => [
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

it('renders a chosen image in every layout that offers the field', function (): void {
    // The layouts that used to drop it silently. Each of these blocks puts an
    // image picker in the inspector, so choosing one and seeing nothing happen
    // reads as a broken canvas — the operator has no way to know the LAYOUT is
    // what refuses it. The two hero variants and the gallery already rendered
    // theirs; these three are the ones that did not.
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $media = $this->runInTenant($tenant, fn (): Media => Media::factory()->create([
        'tenant_id' => $tenant->id,
        'path' => 'media/chosen.jpg',
    ]));

    $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome', 'image_id' => $media->id]],
        ['type' => 'cta', 'data' => ['variant' => 'banner', 'heading' => 'Book now', 'cta_label' => 'Book', 'cta_url' => '/contact', 'image_id' => $media->id]],
        ['type' => 'features', 'data' => ['variant' => 'icon-rows', 'features' => [
            ['title' => 'Deep clean', 'icon' => '✦', 'image_id' => $media->id],
        ]]],
    ]);

    $response = $this->get(sprintf('http://acme.%s/', $this->centralDomain()))->assertOk();

    expect(mb_substr_count((string) $response->getContent(), 'media/chosen.jpg'))->toBe(3)
        // The photo stands in for the glyph rather than joining it.
        ->and($response->getContent())->not->toContain('✦');
});

it('resolves item media however the repeater is keyed, for every block that takes one', function (): void {
    // The shape a repeater arrives in depends on where it came from: storage
    // and the AI writer produce a LIST, but the copy Filament holds while a
    // block is open in the inspector — which is exactly the copy the editor
    // canvas renders for the selected block — keys every item by a uuid.
    // Resolution used to bail on anything that was not a list, so selecting a
    // gallery replaced its photographs with whatever `url` was stored (for an
    // AI-written page, the placeholder) while the published page stayed right.
    //
    // Driven off the contracts rather than a hand-listed set, so a new block
    // with item images is covered the day it is registered.
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $media = $this->runInTenant($tenant, fn (): Media => Media::factory()->create([
        'tenant_id' => $tenant->id,
        'path' => 'media/item.jpg',
    ]));

    $withItemMedia = array_filter(
        resolve(BlockVocabulary::class)->pageTypes(),
        static fn (BlockType $contract): bool => $contract->itemMediaField !== null,
    );

    expect($withItemMedia)->not->toBeEmpty();

    $offenders = $this->runInTenant($tenant, function () use ($withItemMedia, $media): array {
        $offenders = [];

        foreach ($withItemMedia as $type => $contract) {
            $item = [
                (string) $contract->itemMediaField => $media->id,
                // The stale value the resolved URL has to win over: this is
                // what the canvas was falling back to.
                'url' => '/images/placeholder.svg',
            ];

            foreach ([
                'a list' => [$item],
                "Filament's uuid-keyed state" => ['0f9a1b2c-3d4e-4f50-8617-2b0c1d3e4f56' => $item],
            ] as $shape => $items) {
                $resolved = BlockRegistry::resolveMediaUrls([(string) $contract->itemsField => $items]);
                $entry = array_first($resolved[(string) $contract->itemsField]);

                // Unescaped, or the encoder's `media\/item.jpg` never matches.
                if (! str_contains((string) json_encode($entry, JSON_UNESCAPED_SLASHES), 'media/item.jpg')) {
                    $offenders[] = "{$type} loses its item image when the repeater arrives as {$shape}";
                }
            }
        }

        return $offenders;
    });

    expect($offenders)->toBeEmpty();
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
