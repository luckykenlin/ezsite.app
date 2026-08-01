<?php

declare(strict_types=1);

use App\Actions\Pages\StampPresetDefaults;
use App\Design\StylePreset;
use App\Models\Tenant;
use Illuminate\Testing\TestResponse;

/**
 * The shared `<x-site.section>` shell, from the render side.
 *
 * The first test is the whole justification for the shell's design: every page
 * block view used to hard-code its own background and padding, and adopting the
 * shell had to leave all eighteen of them pixel-identical. It pins the DEFAULTS
 * each view declares, so a mistuned `tone`/`spacing` prop — the one mistake this
 * refactor could make silently on every live tenant site — fails here.
 */
function renderBlock(array $block, int $locations = 0): TestResponse
{
    $tenant = Tenant::factory()->withDomain('acme')->create();

    test()->createTenantBusiness(
        $tenant,
        ['name' => 'Corner Cafe', 'tagline' => 'Best brews in town', 'logo_path' => null],
        $locations,
    );

    test()->createTenantPage($tenant, [$block]);

    return test()->get(sprintf('http://acme.%s/', test()->centralDomain()))->assertOk();
}

/**
 * A whole page of blocks at once, for the preset rhythm test below.
 */
function renderBlocks(array $blocks, string $subdomain): TestResponse
{
    $tenant = Tenant::factory()->withDomain($subdomain)->create();

    test()->createTenantBusiness($tenant, ['name' => 'Corner Cafe', 'logo_path' => null], 0);
    test()->createTenantPage($tenant, $blocks);

    return test()->get(sprintf('http://%s.%s/', $subdomain, test()->centralDomain()))->assertOk();
}

it('keeps every block view on the background and spacing it declared before the shell existed', function (array $block, string $tone, string $spacing, int $locations): void {
    $response = renderBlock($block, $locations);

    // Both halves matter: the tone lands on the section element, the spacing on
    // the shell's inner wrapper. A view that passed its padding on as a tone
    // (or forgot it) would still render — just differently.
    $response->assertSeeHtml($tone)->assertSeeHtml('<div class="'.$spacing.'">');
})->with([
    // The plain white majority, kept honest by naming each one: these are the
    // views whose defaults are the least interesting and the easiest to break
    // wholesale with a bad find-and-replace.
    'hero centered-minimal' => [
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Hello']],
        'bg-base-100 text-base-content', 'py-24 md:py-32', 0,
    ],
    'hero left-text-right-image' => [
        ['type' => 'hero', 'data' => ['variant' => 'left-text-right-image', 'heading' => 'Hello']],
        'bg-base-100 text-base-content', 'py-20 md:py-28', 0,
    ],
    // The loudest default in the library, and the only user of the backdrop slot.
    'hero full-bleed-overlay' => [
        ['type' => 'hero', 'data' => ['variant' => 'full-bleed-overlay', 'heading' => 'Hello']],
        'bg-neutral text-neutral-content', 'py-32 md:py-48', 0,
    ],
    'features grid' => [
        ['type' => 'features', 'data' => ['variant' => 'grid', 'heading' => 'Why us']],
        'bg-base-100 text-base-content', 'py-20 md:py-28', 0,
    ],
    'features list' => [
        ['type' => 'features', 'data' => ['variant' => 'list', 'heading' => 'Why us']],
        'bg-base-100 text-base-content', 'py-20 md:py-28', 0,
    ],
    'offerings list' => [
        ['type' => 'offerings', 'data' => ['variant' => 'list', 'heading' => 'Menu']],
        'bg-base-100 text-base-content', 'py-20 md:py-28', 0,
    ],
    'offerings cards' => [
        ['type' => 'offerings', 'data' => ['variant' => 'cards', 'heading' => 'Menu']],
        'bg-base-100 text-base-content', 'py-20 md:py-28', 0,
    ],
    'gallery grid' => [
        ['type' => 'gallery', 'data' => ['variant' => 'grid', 'heading' => 'Our work']],
        'bg-base-100 text-base-content', 'py-20 md:py-28', 0,
    ],
    'gallery masonry' => [
        ['type' => 'gallery', 'data' => ['variant' => 'masonry', 'heading' => 'Our work']],
        'bg-base-100 text-base-content', 'py-20 md:py-28', 0,
    ],
    'faq' => [
        ['type' => 'faq', 'data' => ['heading' => 'Questions']],
        'bg-base-100 text-base-content', 'py-20 md:py-28', 0,
    ],
    'prose' => [
        ['type' => 'prose', 'data' => ['heading' => 'About us']],
        'bg-base-100 text-base-content', 'py-20 md:py-28', 0,
    ],
    // The three deliberate exceptions, which is why they are worth pinning most.
    'testimonials grid is shaded' => [
        ['type' => 'testimonials', 'data' => ['variant' => 'grid', 'heading' => 'Reviews']],
        'bg-base-200 text-base-content', 'py-20 md:py-28', 0,
    ],
    'testimonials carousel is shaded' => [
        ['type' => 'testimonials', 'data' => ['variant' => 'carousel', 'heading' => 'Reviews']],
        'bg-base-200 text-base-content', 'py-20 md:py-28', 0,
    ],
    'cta banner is the brand colour, and tighter' => [
        ['type' => 'cta', 'data' => ['variant' => 'banner', 'heading' => 'Book now', 'cta_label' => 'Call', 'cta_url' => '/contact']],
        'site-tone-accent', 'py-16 md:py-20', 0,
    ],
    'cta boxed is tighter but plain' => [
        ['type' => 'cta', 'data' => ['variant' => 'boxed', 'heading' => 'Book now', 'cta_label' => 'Call', 'cta_url' => '/contact']],
        'bg-base-100 text-base-content', 'py-16 md:py-20', 0,
    ],
    // A heading paints nothing at all: it is a divider inside the page's flow,
    // not a band of its own.
    'heading paints no background' => [
        ['type' => 'heading', 'data' => ['content' => 'Our services', 'level' => 'h2']],
        '<section class="px-4">', 'py-8 md:py-12', 0,
    ],
    // Bound blocks need a location before they render at all.
    'contact split' => [
        ['type' => 'contact', 'data' => ['variant' => 'split', 'heading' => 'Find us']],
        'bg-base-100 text-base-content', 'py-20 md:py-28', 1,
    ],
    'contact stacked' => [
        ['type' => 'contact', 'data' => ['variant' => 'stacked', 'heading' => 'Find us']],
        'bg-base-100 text-base-content', 'py-20 md:py-28', 1,
    ],
]);

it('lets a stored appearance override the view defaults', function (): void {
    renderBlock([
        'type' => 'features',
        'data' => [
            'variant' => 'grid',
            'appearance' => ['tone' => 'inverted', 'spacing' => 'tall'],
            'heading' => 'Why us',
        ],
    ])
        // The whole open tag, not just the tone: the site header is itself
        // `bg-base-100 text-base-content`, so a bare assertDontSee for the
        // default would fail on chrome that has nothing to do with this block.
        ->assertSeeHtml('<section class="bg-neutral text-neutral-content">')
        ->assertSeeHtml('<div class="py-32 md:py-48">');
});

it('overrides one dimension without disturbing the other', function (): void {
    renderBlock([
        'type' => 'testimonials',
        'data' => ['variant' => 'grid', 'appearance' => ['spacing' => 'flush'], 'heading' => 'Reviews'],
    ])
        // The view's own shaded default survives an appearance that says nothing
        // about the background.
        ->assertSeeHtml('bg-base-200 text-base-content')
        ->assertSeeHtml('<div class="py-8 md:py-12">');
});

it('degrades to the view defaults on a malformed stored appearance rather than failing the page', function (): void {
    renderBlock([
        'type' => 'features',
        'data' => [
            'variant' => 'grid',
            // Every shape the render path must survive: an unknown case, a
            // non-string, and a value that is not even a map.
            'appearance' => ['tone' => 'neon', 'spacing' => ['tall']],
            'heading' => 'Why us',
        ],
    ])
        ->assertSeeHtml('bg-base-100 text-base-content')
        ->assertSeeHtml('<div class="py-20 md:py-28">')
        ->assertSee('Why us');
});

it('never leaks the appearance value into the markup as an attribute', function (): void {
    // `data` keys become component attributes, so an appearance the view failed
    // to declare as a prop would land in the DOM — and being an array, would
    // fatal on the way. Guarding the rendered output covers every view at once.
    renderBlock([
        'type' => 'prose',
        'data' => [
            'appearance' => ['tone' => 'muted'],
            'heading' => 'About us',
            'paragraphs' => [['text' => 'We have been here a while.']],
        ],
    ])
        ->assertSeeHtml('bg-base-200 text-base-content')
        ->assertDontSeeHtml('appearance=')
        ->assertSee('We have been here a while.');
});

/*
 * End to end, and the actual point of preset appearances: everything upstream is
 * unit-tested, but only a render proves the whole chain is connected —
 * preset → stamped `data.appearance` → section shell → CSS classes.
 *
 * One preset per test case rather than two in one: each needs its own tenant, and
 * a domain can only be occupied once per test.
 */
it('renders a preset section rhythm end to end', function (StylePreset $preset, string $see, string $dontSee): void {
    $blocks = resolve(StampPresetDefaults::class)->handle([
        ['type' => 'features', 'data' => ['heading' => 'Why us']],
        ['type' => 'gallery', 'data' => ['heading' => 'Our work']],
    ], $preset);

    renderBlocks($blocks, 'acme')
        ->assertSeeHtml($see)
        ->assertDontSeeHtml($dontSee);
})->with([
    // Editorial is the one preset that puts photographs on black, and it runs
    // compact throughout.
    'bold-editorial goes dark and tight' => [
        StylePreset::BoldEditorial,
        '<div class="py-16 md:py-20">',
        '<div class="py-24 md:py-32">',
    ],
    // Coastal never goes dark and gives everything room.
    'calm-coastal stays light and airy' => [
        StylePreset::CalmCoastal,
        '<div class="py-24 md:py-32">',
        'bg-neutral text-neutral-content',
    ],
]);
