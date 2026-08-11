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
    //
    // The reveal marker rides on that same wrapper, so it is pinned here too:
    // it is what App\Design\MotionStyle animates, and a wrapper that lost it
    // would leave the motion token with nothing to act on and no failure
    // anywhere. Inert until a site chooses a motion style.
    $response->assertSeeHtml($tone)->assertSeeHtml('<div data-animate class="'.$spacing.'">');
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
    'features rows' => [
        ['type' => 'features', 'data' => ['variant' => 'rows', 'heading' => 'Why us']],
        'bg-base-100 text-base-content', 'py-20 md:py-28', 0,
    ],
    'offerings' => [
        ['type' => 'offerings', 'data' => ['heading' => 'Menu']],
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
    // A heading paints nothing at all: it is a divider inside the page's flow,
    // not a band of its own.
    'heading paints no background' => [
        ['type' => 'heading', 'data' => ['content' => 'Our services', 'level' => 'h2']],
        '<section class="">', 'py-8 md:py-12', 0,
    ],
    // Bound blocks need a location before they render at all.
    'contact' => [
        ['type' => 'contact', 'data' => ['heading' => 'Find us']],
        'bg-base-100 text-base-content', 'py-20 md:py-28', 1,
    ],
]);

/*
 * The parametric axes, end to end: a stored axis value must change the classes
 * the view emits, and an untouched one must render the contract default. One
 * spot check per axis — the enum→class maps themselves are unit-pinned.
 */
it('renders stored layout axes and falls back to the contract defaults', function (): void {
    renderBlock([
        'type' => 'features',
        'data' => [
            'variant' => 'grid',
            'appearance' => ['columns' => 'two', 'item_style' => 'plain', 'align' => 'start', 'width' => 'narrow'],
            'heading' => 'Why us',
            'features' => [['title' => 'Fresh', 'description' => 'Daily']],
        ],
    ])
        ->assertSeeHtml('grid-cols-1 sm:grid-cols-2')
        ->assertSeeHtml('max-w-3xl')
        // align=start drops the centring; plain items drop the card chrome.
        ->assertDontSeeHtml('site-h2 text-center')
        ->assertDontSeeHtml('site-card bg-base-200');
});

it('renders the contract axis defaults when nothing is stored', function (): void {
    renderBlock([
        'type' => 'features',
        'data' => ['variant' => 'grid', 'heading' => 'Why us', 'features' => [['title' => 'Fresh']]],
    ])
        ->assertSeeHtml('grid-cols-1 sm:grid-cols-2 lg:grid-cols-3')
        ->assertSeeHtml('max-w-7xl')
        ->assertSeeHtml('site-h2 text-center')
        ->assertSeeHtml('site-card bg-base-200');
});

it('lifts cards onto a lighter surface when the tone axis darkens the band', function (): void {
    // The latent bug the tone-aware item surface fixes: bg-base-200 cards on
    // a muted bg-base-200 band used to vanish.
    renderBlock([
        'type' => 'features',
        'data' => [
            'variant' => 'grid',
            'appearance' => ['tone' => 'muted'],
            'heading' => 'Why us',
            'features' => [['title' => 'Fresh']],
        ],
    ])
        ->assertSeeHtml('site-card bg-base-100')
        ->assertDontSeeHtml('site-card bg-base-200');
});

it('re-expresses the retired list variants as axis combinations', function (): void {
    // The old offerings/list look: one column, no cards, ruled menu rows.
    renderBlock([
        'type' => 'offerings',
        'data' => [
            'appearance' => ['columns' => 'one', 'item_style' => 'plain', 'width' => 'narrow', 'align' => 'start'],
            'heading' => 'Menu',
            'items' => [['name' => 'Espresso', 'price' => '$4']],
        ],
    ])
        ->assertSeeHtml('site-menu-row')
        ->assertSeeHtml('max-w-3xl')
        ->assertSee('Espresso');
});

/*
 * The edge-to-edge width, which is three coordinated decisions rather than one
 * class: the photographs lose the measure AND the side padding, the heading
 * keeps both (a title pinned to the left edge of a 1440px window is a missing
 * margin, not a design), and the frames lose their radius, because a corner
 * only reads as a corner against a margin.
 */
it('bleeds the photographs and keeps the words inside the page margin', function (): void {
    $bled = renderBlock([
        'type' => 'gallery',
        'data' => [
            'variant' => 'grid',
            'appearance' => ['width' => 'full'],
            'heading' => 'The corner shop',
            'images' => [['url' => '/images/placeholder.svg', 'alt' => 'A photo']],
        ],
    ]);

    $bled->assertSeeHtml('max-w-none')
        // The heading's own container still carries the measure and padding.
        ->assertSeeHtml('mx-auto px-6 max-w-7xl')
        ->assertSeeHtml('<div class="overflow-hidden">')
        ->assertDontSeeHtml('rounded-box overflow-hidden');
});

it('keeps the radius and the padding on every contained width', function (): void {
    // The other half of the pair: without it the assertion above passes for a
    // view that simply stopped rounding its photographs.
    renderBlock([
        'type' => 'gallery',
        'data' => [
            'variant' => 'grid',
            'appearance' => ['width' => 'wide'],
            'heading' => 'The corner shop',
            'images' => [['url' => '/images/placeholder.svg', 'alt' => 'A photo']],
        ],
    ])
        ->assertSeeHtml('rounded-box overflow-hidden')
        ->assertDontSeeHtml('max-w-none');
});

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
        ->assertSeeHtml('<div data-animate class="py-32 md:py-48">');
});

it('overrides one dimension without disturbing the other', function (): void {
    renderBlock([
        'type' => 'testimonials',
        'data' => ['variant' => 'grid', 'appearance' => ['spacing' => 'flush'], 'heading' => 'Reviews'],
    ])
        // The view's own shaded default survives an appearance that says nothing
        // about the background.
        ->assertSeeHtml('bg-base-200 text-base-content')
        ->assertSeeHtml('<div data-animate class="py-8 md:py-12">');
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
        ->assertSeeHtml('<div data-animate class="py-20 md:py-28">')
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
 * The one view that opts out of the shell's reveal, and the reason the `animate`
 * prop exists at all: it stages its own children with a CSS animation instead,
 * and a wrapper revealing at the same time would double every child's travel.
 * Both halves are asserted together because either alone is satisfiable by a
 * mistake — a view that opted out and staged nothing would simply never move.
 */
it('lets a view stage its own children instead of revealing as one block', function (): void {
    renderBlock([
        'type' => 'hero',
        'data' => ['variant' => 'full-viewport-quiet', 'heading' => 'Hello', 'subheading' => 'A quiet line'],
    ])
        ->assertDontSeeHtml('<div data-animate class="py-32 md:py-48">')
        ->assertSeeHtml('site-stage');
});

/*
 * The height concession this variant makes for a photograph. A full-height
 * opening with an image parked below the fold reads as an accident, so the
 * viewport-filling height is what gives way — and the scroll cue goes with it,
 * since there is now something visibly below to scroll to.
 */
it('drops the viewport-filling height from the quiet hero when it carries a photograph', function (array $data, bool $fillsViewport): void {
    // Each case needs its own tenant, so they are a dataset rather than two
    // renders in one test — a domain can only be occupied once per test.
    $response = renderBlocks([['type' => 'hero', 'data' => $data]], 'acme');

    $fillsViewport
        ? $response->assertSeeHtml('min-h-[85svh]')->assertSeeHtml('site-scroll-cue')
        : $response->assertDontSeeHtml('min-h-[85svh]')->assertDontSeeHtml('site-scroll-cue')->assertSeeHtml('room.jpg');
})->with([
    'type only' => [['variant' => 'full-viewport-quiet', 'heading' => 'Hello'], true],
    'with a photograph' => [['variant' => 'full-viewport-quiet', 'heading' => 'Hello', 'image_url' => 'https://example.test/room.jpg'], false],
]);

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
        '<div data-animate class="py-16 md:py-20">',
        '<div data-animate class="py-24 md:py-32">',
    ],
    // Coastal never goes dark and gives everything room.
    'calm-coastal stays light and airy' => [
        StylePreset::CalmCoastal,
        '<div data-animate class="py-24 md:py-32">',
        'bg-neutral text-neutral-content',
    ],
]);
