<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Location;
use App\Models\Tenant;
use App\Site\Blocks\SectionColumns;
use App\Themes\Definitions\NailLuxe;
use App\Themes\Theme;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Illuminate\Testing\TestResponse;

/**
 * The nail-luxe theme from the render side: a tenant on the theme, one page,
 * one block. Rendered through the real HTTP stack so the view resolution,
 * the section shell and the HEAD_END stylesheet hook are all the production
 * path.
 *
 * Returns the response plus every view the render touched, so a case can
 * prove no default-theme view leaked into a nail-luxe page.
 *
 * @param  array<string, mixed>  $block
 * @return array{TestResponse, list<string>}
 */
function renderNailLuxeBlock(array $block): array
{
    $tenant = Tenant::factory()->withDomain('studio')->create(['theme' => Theme::NailLuxe]);

    test()->runInTenant($tenant, function () use ($tenant): void {
        $business = Business::factory()->themed(NailLuxe::BLUSH_PLUM, Theme::NailLuxe)->create([
            'tenant_id' => $tenant->id,
            'name' => 'Polish Room',
            'tagline' => 'Nails, painted slowly',
            'contact_phone' => '(555) 010-0199',
            'contact_email' => 'hello@polish.test',
        ]);

        Location::factory()->create([
            'tenant_id' => $tenant->id,
            'business_id' => $business->id,
            'is_primary' => true,
            'address_line1' => '12 Rose Lane',
            'city' => 'Savannah',
        ]);
    });

    test()->createTenantPage($tenant, [$block]);

    // The suite's Vite stub renders nothing, which would hide WHICH
    // stylesheet the head asked for; this one names its entry points.
    test()->swap(Vite::class, new class extends Vite
    {
        public function __invoke($entrypoints, $buildDirectory = null): HtmlString
        {
            return new HtmlString('<link data-vite-entry="'.implode(',', (array) $entrypoints).'">');
        }

        public function __call($method, $parameters): string
        {
            return '';
        }

        public function fonts($aliases = null): HtmlString
        {
            return new HtmlString('');
        }
    });

    $views = [];

    View::composer('*', function ($view) use (&$views): void {
        $views[] = $view->getName();
    });

    $response = test()->get(sprintf('http://studio.%s/', test()->centralDomain()))->assertOk();

    return [$response, $views];
}

it('renders every block and layout the theme lists through its own view', function (array $block, string $marker): void {
    [$response, $views] = renderNailLuxeBlock($block);

    $response
        ->assertSeeHtml($marker)
        ->assertSeeHtml('data-vite-entry="resources/themes/nail-luxe/theme.css"')
        ->assertDontSeeHtml('resources/themes/default/theme.css');

    expect(array_filter($views, fn (string $view): bool => str_starts_with($view, 'themes::default.')))->toBeEmpty()
        ->and($views)->toContain(Theme::NailLuxe->blockView($block['type'], $block['data']['variant'] ?? null));
})->with([
    'header studio' => [['type' => 'header', 'data' => ['variant' => 'studio', 'nav_links' => [['label' => 'Services', 'url' => '/services']], 'cta_label' => 'Book', 'cta_url' => '/contact']], 'site-strip'],
    'header centered' => [['type' => 'header', 'data' => ['variant' => 'centered', 'nav_links' => [['label' => 'Services', 'url' => '/services']]]], 'site-header-centered'],
    'footer card' => [['type' => 'footer', 'data' => ['variant' => 'card', 'nav_links' => [['label' => 'Visit', 'url' => '/contact']]]], 'site-footer-card'],
    'hero arch' => [['type' => 'hero', 'data' => ['variant' => 'arch', 'heading' => 'Nails, done *beautifully*']], 'site-hero-arch'],
    'hero editorial' => [['type' => 'hero', 'data' => ['variant' => 'editorial', 'heading' => 'The *menu*']], 'site-hero-bloom'],
    'heading' => [['type' => 'heading', 'data' => ['content' => 'Our *services*', 'level' => 'h2']], '<em class="site-accent">'],
    'prose centered' => [['type' => 'prose', 'data' => ['variant' => 'centered', 'heading' => 'How we work', 'paragraphs' => [['text' => 'One client per technician.']]]], 'site-prose'],
    'offerings price-list' => [['type' => 'offerings', 'data' => ['variant' => 'price-list', 'items' => [['group' => 'Hands', 'name' => 'Gel manicure', 'price' => '$55']]]], 'site-price-row'],
    'offerings category-cards' => [['type' => 'offerings', 'data' => ['variant' => 'category-cards', 'items' => [['group' => 'Hands', 'name' => 'Gel manicure'], ['group' => 'Hands', 'name' => 'Classic manicure']]]], '2 services'],
    'gallery arch-grid' => [['type' => 'gallery', 'data' => ['variant' => 'arch-grid', 'images' => [['alt' => 'A set']]]], 'site-arch-tile'],
    'gallery mosaic' => [['type' => 'gallery', 'data' => ['variant' => 'mosaic', 'images' => [['alt' => 'A set']]]], 'site-mosaic-tile'],
    'team' => [['type' => 'team', 'data' => ['members' => [['name' => 'Ana', 'role' => 'Nail artist']]]], 'site-artist'],
    'testimonials cards' => [['type' => 'testimonials', 'data' => ['variant' => 'cards', 'testimonials' => [['quote' => 'Lovely', 'author' => 'Jo']]]], 'site-quote-card'],
    'testimonials spotlight' => [['type' => 'testimonials', 'data' => ['variant' => 'spotlight', 'testimonials' => [['quote' => 'Lovely', 'author' => 'Jo']]]], 'site-spotlight-quote'],
    'faq' => [['type' => 'faq', 'data' => ['questions' => [['question' => 'Walk-ins?', 'answer' => 'Sometimes.']]]], 'site-faq-item'],
    'visit' => [['type' => 'visit', 'data' => ['heading' => 'Visit the *studio*']], 'site-visit'],
    'contact' => [['type' => 'contact', 'data' => ['heading' => 'Send a message']], 'site-contact-cards'],
    'cta offer' => [['type' => 'cta', 'data' => ['variant' => 'offer', 'heading' => 'Book *now*', 'cta_label' => 'Book', 'cta_url' => '/contact']], 'site-offer-card'],
    'credentials' => [['type' => 'credentials', 'data' => ['heading' => 'Rooted in *care*', 'figures' => [['value' => '4.9', 'label' => 'Rating']], 'quote' => 'We take our time.']], 'site-figure-value'],
]);

/*
 * The dataset above is hand-written, so this is what keeps it honest: every
 * view the theme's definition promises exists on disk. A layout added to the
 * definition without its view would otherwise fail only on a live site.
 */
it('has a view on disk for every block type and layout it declares', function (): void {
    $missing = [];

    foreach (Theme::NailLuxe->definition()->blocks as $type => $block) {
        $views = $block->hasVariants()
            ? array_map(fn (string $variant): string => Theme::NailLuxe->blockView($type, $variant), array_keys($block->variants))
            : [Theme::NailLuxe->blockView($type)];

        foreach ($views as $view) {
            if (! View::exists($view)) {
                $missing[] = $view;
            }
        }
    }

    expect($missing)->toBeEmpty();
});

/*
 * A fresh site has no photographs until the stock-photo job runs, and an
 * operator may never add one. The arches must still read as designed: a
 * tinted frame with the ornament, never an empty <img> or a collapsed box.
 */
it('keeps an unphotographed arch as a designed frame, and frames a photograph when there is one', function (?string $image, string $expected): void {
    [$response] = renderNailLuxeBlock(['type' => 'hero', 'data' => ['variant' => 'arch', 'heading' => 'Hello', 'image_url' => $image]]);

    $response->assertSeeHtml($expected);
})->with([
    'no photograph' => [null, 'site-arch-empty'],
    'a photograph' => ['https://example.test/hands.jpg', 'class="site-arch-img"'],
]);

/*
 * The stock-photo job fills every offerings item's photo, and both price
 * layouts used to drop it on the floor — a services page with a photograph
 * for every manicure rendered none. The price list leads a photographed row
 * with a thumbnail and leaves a bare row bare; a category card is covered by
 * its group's FIRST photographed item, skipping any without one.
 */
it('shows the service photographs in both price layouts', function (string $variant, string $photographed, string $bare): void {
    [$response] = renderNailLuxeBlock(['type' => 'offerings', 'data' => ['variant' => $variant, 'items' => [
        ['group' => 'Hands', 'name' => 'Classic manicure', 'price' => '$35'],
        ['group' => 'Hands', 'name' => 'Gel manicure', 'price' => '$55', 'image_url' => 'https://example.test/gel.jpg'],
        ['group' => 'Hands', 'name' => 'Builder gel', 'price' => '$70', 'image_url' => 'https://example.test/builder.jpg'],
        ['group' => 'Feet', 'name' => 'Pedicure', 'price' => '$50'],
    ]]]);

    $response->assertSeeHtml($photographed)
        ->assertSeeHtml($bare)
        ->assertDontSeeHtml('builder.jpg" alt="" loading="lazy" class="site-arch-img"');
})->with([
    'price list: thumbnail on the photographed row only' => ['price-list', 'src="https://example.test/gel.jpg" alt="" loading="lazy" class="site-price-thumb"', '<div class="site-price-row">'],
    'category cards: first photograph covers, an unphotographed group keeps the frame' => ['category-cards', 'src="https://example.test/gel.jpg" alt="" loading="lazy" class="site-arch-img"', 'site-arch-empty'],
]);

it('sets four categories two by two instead of stranding the fourth card', function (int $groups, SectionColumns $expected): void {
    $items = array_map(fn (int $group): array => ['group' => 'Group '.$group, 'name' => 'Service'], range(1, $groups));

    [$response] = renderNailLuxeBlock(['type' => 'offerings', 'data' => ['variant' => 'category-cards', 'items' => $items]]);

    $response->assertSeeHtml('grid gap-6 '.$expected->classes().'"');
})->with([
    'three across' => [3, SectionColumns::Three],
    'four, two by two' => [4, SectionColumns::Two],
]);

it('draws at most three figures, however many are stored', function (): void {
    [$response] = renderNailLuxeBlock(['type' => 'credentials', 'data' => ['figures' => [
        ['value' => '1', 'label' => 'One'],
        ['value' => '2', 'label' => 'Two'],
        ['value' => '3', 'label' => 'Three'],
        ['value' => '4', 'label' => 'Four'],
    ]]]);

    $response->assertSeeText('Three')->assertDontSeeText('Four');
});

it('moves a footer link to a social network into its own Follow column', function (): void {
    [$response] = renderNailLuxeBlock(['type' => 'footer', 'data' => ['variant' => 'card', 'nav_links' => [
        ['label' => 'Services', 'url' => '/services'],
        ['label' => 'Instagram', 'url' => 'https://instagram.com/polishroom'],
    ]]]);

    $response->assertSeeInOrder(['Explore', 'Services', 'Visit', 'Follow', 'Instagram']);
});
