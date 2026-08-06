<?php

declare(strict_types=1);

use App\Actions\ApplySiteDraft;
use App\Actions\SaveSiteChrome;
use App\Design\StylePreset;
use App\Enums\PageStatus;
use App\Models\Business;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\Tenant;

/**
 * @param  list<array{type: string, data: array<string, mixed>}>  $blocks
 * @return array{slug: string, title: string, metaDescription: string|null, blocks: list<array{type: string, data: array<string, mixed>}>}
 */
function draftPage(string $slug, string $title, array $blocks, ?string $metaDescription = null): array
{
    return ['slug' => $slug, 'title' => $title, 'metaDescription' => $metaDescription, 'blocks' => $blocks];
}

/**
 * @return list<array{type: string, data: array<string, mixed>}>
 */
function draftHomeBlocks(): array
{
    return [
        ['type' => 'hero', 'data' => ['heading' => 'Welcome']],
        ['type' => 'features', 'data' => ['heading' => 'Why us', 'features' => [['title' => 'Handmade']]]],
        ['type' => 'cta', 'data' => ['heading' => 'Come by']],
    ];
}

/**
 * @param  non-empty-list<array{slug: string, title: string, metaDescription: string|null, blocks: list<array{type: string, data: array<string, mixed>}>}>  $pages
 */
function applyDraftFor(Tenant $tenant, array $pages, StylePreset $preset = StylePreset::WarmCraft, bool $overwritePublished = false): Page
{
    return test()->runInTenant($tenant, fn (): Page => resolve(ApplySiteDraft::class)->handle(
        Business::query()->firstOrFail(),
        ['preset' => $preset, 'pages' => $pages],
        $overwritePublished,
    ));
}

it('creates every page as a draft, back-fills the preset and stamps the navigation', function (): void {
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, ['name' => 'Corner Cafe'], 1);

    $home = applyDraftFor($tenant, [
        draftPage('/', 'Corner Cafe — Home', draftHomeBlocks(), 'Fresh sourdough daily.'),
        draftPage('/about', 'Our Story', draftHomeBlocks()),
    ]);

    $about = Page::query()->where('slug', '/about')->sole();

    expect($home->slug)->toBe('/')
        ->and($home->tenant_id)->toBe($tenant->id)
        ->and($home->status)->toBe(PageStatus::Draft)
        ->and($home->seo_description)->toBe('Fresh sourdough daily.')
        // The preset speaks where the draft was silent: WarmCraft's features
        // layout and its shaded, airy band.
        ->and($home->blocks[1]['data']['variant'])->toBe('alternating')
        ->and($home->blocks[1]['data']['appearance'])->toBe(['tone' => 'muted', 'spacing' => 'airy'])
        ->and($about->status)->toBe(PageStatus::Draft)
        ->and($about->seo_description)->toBeNull();

    // Home leads the navigation and is labelled "Home", not by its title.
    expect(SiteSetting::query()->sole()->header[0]['data']['nav_links'])->toBe([
        ['label' => 'Home', 'url' => '/'],
        ['label' => 'Our Story', 'url' => '/about'],
    ]);
});

it('updates the existing draft at a slug instead of creating a second page', function (): void {
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, [], 1);

    $first = applyDraftFor($tenant, [draftPage('/', 'First take', draftHomeBlocks())]);
    $second = applyDraftFor($tenant, [draftPage('/', 'Second take', draftHomeBlocks())]);

    expect($second->getKey())->toBe($first->getKey())
        ->and(Page::query()->count())->toBe(1)
        ->and(Page::query()->sole()->title)->toBe('Second take');
});

it('skips a published extra page and leaves it out of the navigation', function (): void {
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, [], 1);

    $this->runInTenant($tenant, function () use ($tenant): void {
        Page::query()->create([
            'tenant_id' => $tenant->id,
            'title' => 'Hand-written About',
            'slug' => '/about',
            'layout' => 'main',
            'blocks' => [['type' => 'prose', 'data' => ['heading' => 'Precious copy.']]],
            'status' => PageStatus::Published,
        ]);
    });

    applyDraftFor($tenant, [
        draftPage('/', 'Home', draftHomeBlocks()),
        draftPage('/about', 'Generated About', draftHomeBlocks()),
    ]);

    $about = Page::query()->where('slug', '/about')->sole();

    expect($about->title)->toBe('Hand-written About')
        ->and($about->status)->toBe(PageStatus::Published)
        ->and(SiteSetting::query()->sole()->header[0]['data']['nav_links'])
        ->toBe([['label' => 'Home', 'url' => '/']]);
});

it('replaces a published extra page when the caller owns every page on the site', function (): void {
    // The demo-tenant path: `demo:seed` re-runs on every deploy and must land
    // the current template over last deploy's published pages, because nobody
    // hand-edits a demo site.
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, [], 1);

    $this->runInTenant($tenant, function () use ($tenant): void {
        Page::query()->create([
            'tenant_id' => $tenant->id,
            'title' => 'Last deploy',
            'slug' => '/about',
            'layout' => 'main',
            'blocks' => [],
            'status' => PageStatus::Published,
        ]);
    });

    applyDraftFor($tenant, [
        draftPage('/', 'Home', draftHomeBlocks()),
        draftPage('/about', 'This deploy', draftHomeBlocks()),
    ], overwritePublished: true);

    $about = Page::query()->where('slug', '/about')->sole();

    expect($about->title)->toBe('This deploy')
        ->and($about->status)->toBe(PageStatus::Draft)
        ->and(Page::query()->count())->toBe(2);
});

it('leaves a hand-shaped navigation alone', function (): void {
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, [], 1);

    $this->runInTenant($tenant, function (): void {
        resolve(SaveSiteChrome::class)->handle(
            [['type' => 'header', 'data' => ['nav_links' => [['label' => 'Mine', 'url' => '/']]]]],
            null,
        );
    });

    applyDraftFor($tenant, [draftPage('/', 'Home', draftHomeBlocks())]);

    expect(SiteSetting::query()->sole()->header[0]['data']['nav_links'])
        ->toBe([['label' => 'Mine', 'url' => '/']]);
});
