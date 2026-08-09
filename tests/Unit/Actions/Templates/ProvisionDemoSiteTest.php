<?php

declare(strict_types=1);

use App\Actions\Templates\ProvisionDemoSite;
use App\Enums\PageStatus;
use App\Models\Business;
use App\Models\LibraryPhoto;
use App\Models\Location;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Site\Blocks\BlockShape;
use App\StockPhotos\StockPhotoProvider;
use App\Templates\SiteTemplate;
use Illuminate\Support\Facades\Http;

function provisionDemo(SiteTemplate $template, bool $skipPhotos = true): Tenant
{
    return resolve(ProvisionDemoSite::class)->handle($template, $skipPhotos);
}

it('publishes a complete site for every template', function (SiteTemplate $template): void {
    $definition = $template->definition();
    $tenant = provisionDemo($template);

    expect($tenant->is_demo)->toBeTrue()
        ->and($tenant->template)->toBe($template)
        ->and($tenant->domain?->domain)->toBe($template->demoSubdomain());

    $this->runInTenant($tenant, function () use ($definition, $template): void {
        $business = Business::query()->sole();
        $pages = Page::query()->orderBy('id')->get();

        expect($business->name)->toBe($definition->demoProfile->name)
            ->and($business->category)->toBe($definition->category)
            ->and($business->brand_primary)->toBe($definition->brandPrimary)
            // The MERGED tokens, so a template that overrides one axis of its
            // preset keeps the override — and keeps the preset marker with it.
            ->and($business->design_tokens->toArray())->toBe($definition->tokens()->toArray())
            ->and(Location::query()->sole()->city)->toBe($definition->demoProfile->city)
            ->and($pages)->toHaveSameSize($definition->pages)
            ->and($pages->pluck('status')->unique()->all())->toBe([PageStatus::Published])
            ->and($pages->pluck('slug')->all())->toBe(array_column($definition->pages, 'slug'));

        // The template's own navigation labels survive: ApplySiteDraft only
        // stamps a generated nav when the tenant has none, and the chrome is
        // saved first.
        $header = SiteSetting::query()->sole()->header;

        expect($header[0]['data']['nav_links'])->toBe($definition->chrome[0]['data']['nav_links']);

        // Every page is real content, not a placeholder scaffold.
        foreach ($pages as $page) {
            expect($page->title)->not->toContain('{')
                ->and(json_encode($page->blocks))->not->toContain('{business_name}');
        }

        expect($template->demoSubdomain())->toStartWith('demo-');
    });
})->with(fn (): array => array_map(
    fn (SiteTemplate $template): array => [$template],
    SiteTemplate::cases(),
));

it('re-runs onto the same tenant, replacing last deploy s published pages', function (): void {
    $template = SiteTemplate::ChineseRestaurant;

    $first = provisionDemo($template);

    $this->runInTenant($first, function (): void {
        Page::query()->where('slug', '/')->update(['title' => 'Edited between deploys']);
    });

    $second = provisionDemo($template);

    expect($second->getKey())->toBe($first->getKey())
        ->and(Tenant::query()->count())->toBe(1);

    $this->runInTenant($second, function () use ($template): void {
        expect(Business::query()->count())->toBe(1)
            ->and(Location::query()->count())->toBe(1)
            ->and(Page::query()->count())->toBe(count($template->definition()->pages))
            // Published pages are overwritten here, unlike on the AI path —
            // nobody hand-edits a demo site, and the deploy is the source of
            // truth for what it says.
            ->and(Page::query()->where('slug', '/')->sole()->title)->not->toBe('Edited between deploys');
    });
});

it('pre-warms the shared library and lands real photographs on the pages', function (): void {
    $provider = $this->poolStockPhotoProvider();
    $this->app->instance(StockPhotoProvider::class, $provider);
    Http::fake(['images.pexels.com/*' => Http::response('jpeg-bytes')]);

    $template = SiteTemplate::ChineseRestaurant;
    $tenant = provisionDemo($template, skipPhotos: false);

    expect($provider->searches)->not->toBeEmpty()
        ->and(array_column($provider->searches, 'query'))
        ->toContain($template->definition()->photoQueries[0]->query)
        // Imported centrally: the library is shared, so the next tenant to
        // want a banquet table spends nothing.
        ->and(LibraryPhoto::query()->count())->toBeGreaterThan(0);

    $this->runInTenant($tenant, function (): void {
        $home = Page::query()->where('slug', '/')->sole();
        $hero = $home->blocks[0];

        expect($hero['type'])->toBe('hero')
            ->and($hero['data']['image_id'] ?? null)->not->toBeNull()
            // The transit key is consumed by the image pass, so no published
            // page carries one.
            ->and($hero['data'])->not->toHaveKey(BlockShape::IMAGE_QUERY_KEY);
    });
});

it('still publishes a site when the photo provider finds nothing', function (): void {
    // The no-key path, which every local machine and CI runner is on: the
    // NullProvider search is empty, nothing imports, and the pages ship
    // photo-less rather than not at all.
    $tenant = provisionDemo(SiteTemplate::MassageSpa, skipPhotos: false);

    expect(LibraryPhoto::query()->count())->toBe(0);

    $this->runInTenant($tenant, function (): void {
        expect(Page::query()->where('status', PageStatus::Published)->count())->toBeGreaterThan(0);
    });
});

it('renders the header above the page and the footer below it, once each', function (): void {
    // The site chrome has two slots and the template declares two blocks; the
    // pairing has to survive provisioning. It did not: the whole chrome list
    // went into the header argument, so every demo site opened with its own
    // footer stacked under the navigation.
    $tenant = provisionDemo(SiteTemplate::ChineseRestaurant);

    $this->runInTenant($tenant, function (): void {
        $settings = SiteSetting::query()->sole();

        expect(array_column($settings->header, 'type'))->toBe(['header'])
            ->and(array_column($settings->footer, 'type'))->toBe(['footer']);
    });
});
