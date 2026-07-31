<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

/**
 * Cross-block render behavior shared by every page block: variant→layout
 * routing, XSS escaping, and skip-with-warning on unresolved binds.
 * Block-specific view logic stays in the per-block test files.
 */
it('renders each block variant with its own layout', function (array $block, array $see, string $marker, string $setup): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();

    if ($setup !== 'none') {
        $this->createTenantBusiness(
            $tenant,
            ['name' => 'Corner Cafe', 'tagline' => 'Best brews in town', 'logo_path' => null],
            $setup === 'business with location' ? 1 : 0,
        );
    }

    $this->createTenantPage($tenant, [$block]);

    $response = $this->get(sprintf('http://acme.%s/', $this->centralDomain()))->assertOk();

    foreach ($see as $text) {
        $response->assertSee($text);
    }

    $response->assertSee($marker, false);
})->with([
    'hero centered-minimal' => [
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome friends']],
        ['Welcome friends'], 'max-w-3xl', 'none',
    ],
    'hero left-text-right-image' => [
        ['type' => 'hero', 'data' => ['variant' => 'left-text-right-image', 'heading' => 'Welcome friends']],
        ['Welcome friends'], 'md:grid-cols-2', 'none',
    ],
    'hero full-bleed-overlay' => [
        ['type' => 'hero', 'data' => ['variant' => 'full-bleed-overlay', 'heading' => 'Welcome friends']],
        ['Welcome friends'], 'bg-neutral', 'none',
    ],
    'cta banner' => [
        ['type' => 'cta', 'data' => [
            'variant' => 'banner',
            'heading' => 'Ready to book?',
            'body' => 'Slots fill up fast.',
            'cta_label' => 'Book now',
            'cta_url' => '/contact',
            'secondary_label' => 'Call us',
            'secondary_url' => 'tel:+15551234567',
        ]],
        ['Ready to book?', 'Book now', 'Call us'], 'bg-primary text-primary-content', 'none',
    ],
    'cta boxed' => [
        ['type' => 'cta', 'data' => [
            'variant' => 'boxed',
            'heading' => 'Ready to book?',
            'cta_label' => 'Book now',
            'cta_url' => '/contact',
            'secondary_label' => 'Call us',
            'secondary_url' => 'tel:+15551234567',
        ]],
        ['Ready to book?', 'Book now', 'Call us'], 'card-actions', 'none',
    ],
    'features grid' => [
        ['type' => 'features', 'data' => [
            'variant' => 'grid',
            'heading' => 'Why choose us',
            'intro' => 'Three reasons that matter.',
            'features' => [
                ['icon' => '⭐', 'title' => 'Fast turnaround', 'description' => 'Same-day service'],
                ['icon' => '🏆', 'title' => 'Award winning', 'description' => 'Voted #1 locally'],
            ],
        ]],
        ['Why choose us', 'Fast turnaround', 'Award winning'], 'lg:grid-cols-3', 'none',
    ],
    'features list' => [
        ['type' => 'features', 'data' => [
            'variant' => 'list',
            'heading' => 'Why choose us',
            'features' => [['title' => 'Fast turnaround', 'description' => 'Same-day service']],
        ]],
        ['Why choose us', 'Fast turnaround'], 'divide-y', 'none',
    ],
    // An item that carries a photo and a destination — what turns this block
    // from a benefits list into the service list operators kept asking for.
    // The stretched-link overlay is what makes the whole card clickable while
    // leaving exactly ONE link, named by the title.
    'features grid with an item image and link' => [
        ['type' => 'features', 'data' => [
            'variant' => 'grid',
            'features' => [[
                'title' => 'Deep clean',
                'description' => 'Two hours, whole house.',
                'image_url' => 'https://example.com/clean.jpg',
                'link_url' => '/services/deep-clean',
            ]],
        ]],
        ['Deep clean', 'https://example.com/clean.jpg', '/services/deep-clean'], 'after:absolute', 'none',
    ],
    'features list with an item image and link' => [
        ['type' => 'features', 'data' => [
            'variant' => 'list',
            'features' => [[
                'title' => 'Deep clean',
                'image_url' => 'https://example.com/clean.jpg',
                'link_url' => '/services/deep-clean',
            ]],
        ]],
        ['Deep clean', 'https://example.com/clean.jpg'], 'after:absolute', 'none',
    ],
    // One block for menus, service lists and packages alike — what differs
    // between those is the content, not the layout.
    'offerings list' => [
        ['type' => 'offerings', 'data' => [
            'variant' => 'list',
            'heading' => 'Our menu',
            'items' => [
                ['group' => 'Starters', 'name' => 'Bruschetta', 'price' => '$9', 'description' => 'Tomato, basil, garlic.'],
                ['group' => 'Mains', 'name' => 'Margherita', 'price' => '$18'],
            ],
        ]],
        ['Our menu', 'Starters', 'Bruschetta', '$9', 'Mains', 'Margherita'], 'tabular-nums', 'none',
    ],
    'offerings cards' => [
        ['type' => 'offerings', 'data' => [
            'variant' => 'cards',
            'heading' => 'Packages',
            'items' => [
                ['name' => 'Half day', 'price' => 'from $400', 'image_url' => 'https://example.com/shoot.jpg'],
            ],
        ]],
        ['Packages', 'Half day', 'from $400', 'https://example.com/shoot.jpg'], 'lg:grid-cols-3', 'none',
    ],
    // Answers stay visible rather than collapsing: the editor canvas swallows
    // clicks, so an accordion would hide the copy the assistant just wrote.
    'faq' => [
        ['type' => 'faq', 'data' => [
            'heading' => 'Questions',
            'intro' => 'The ones we get most.',
            'questions' => [
                ['question' => 'Do you deliver?', 'answer' => 'Within five miles, yes.'],
            ],
        ]],
        ['Questions', 'The ones we get most.', 'Do you deliver?', 'Within five miles, yes.'], '<dl', 'none',
    ],
    // The only block that holds prose. max-w-3xl is the reading measure, not
    // the 7xl the grid blocks use.
    'prose' => [
        ['type' => 'prose', 'data' => [
            'heading' => 'About us',
            'paragraphs' => [
                ['text' => 'We opened in 1998.'],
                ['text' => 'We have been here ever since.'],
            ],
        ]],
        ['About us', 'We opened in 1998.', 'We have been here ever since.'], 'max-w-3xl', 'none',
    ],
    'gallery grid' => [
        ['type' => 'gallery', 'data' => [
            'variant' => 'grid',
            'heading' => 'Our work',
            'images' => [
                ['url' => 'https://example.com/one.jpg', 'alt' => 'First shot', 'caption' => 'Signature look'],
                ['url' => 'https://example.com/two.jpg'],
            ],
        ]],
        ['Our work', 'https://example.com/one.jpg', 'Signature look'], 'sm:grid-cols-3', 'none',
    ],
    'gallery masonry' => [
        ['type' => 'gallery', 'data' => [
            'variant' => 'masonry',
            'heading' => 'Our work',
            'images' => [['url' => 'https://example.com/one.jpg', 'caption' => 'Signature look']],
        ]],
        ['Our work', 'https://example.com/one.jpg', 'Signature look'], 'columns-2', 'none',
    ],
    'testimonials grid' => [
        ['type' => 'testimonials', 'data' => [
            'variant' => 'grid',
            'heading' => 'What clients say',
            'testimonials' => [
                ['quote' => 'Absolutely wonderful service', 'author' => 'Amy Chen', 'role' => 'Regular', 'avatar_url' => 'https://example.com/amy.jpg'],
                ['quote' => 'Best in town', 'author' => 'Ben Wu'],
            ],
        ]],
        ['What clients say', 'Absolutely wonderful service', 'Amy Chen', 'Ben Wu'], 'md:grid-cols-2', 'none',
    ],
    'testimonials carousel' => [
        ['type' => 'testimonials', 'data' => [
            'variant' => 'carousel',
            'heading' => 'What clients say',
            'testimonials' => [['quote' => 'Absolutely wonderful service', 'author' => 'Amy Chen']],
        ]],
        ['What clients say', 'Absolutely wonderful service', 'Amy Chen'], 'carousel-item', 'none',
    ],
    'header simple' => [
        ['type' => 'header', 'data' => [
            'variant' => 'simple',
            'nav_links' => [['label' => 'About', 'url' => '/about'], ['label' => 'Menu', 'url' => '/menu']],
            'cta_label' => 'Book a table',
            'cta_url' => '/contact',
        ]],
        ['Corner Cafe', 'About', 'Menu', 'Book a table'], 'navbar-end', 'business',
    ],
    'header centered' => [
        ['type' => 'header', 'data' => [
            'variant' => 'centered',
            'nav_links' => [['label' => 'About', 'url' => '/about']],
            'cta_label' => 'Book a table',
            'cta_url' => '/contact',
        ]],
        ['Corner Cafe', 'About', 'Book a table'], 'flex-col items-center', 'business',
    ],
    'footer columns' => [
        ['type' => 'footer', 'data' => [
            'variant' => 'columns',
            'nav_links' => [['label' => 'Privacy', 'url' => '/privacy']],
            'note' => 'Licensed and insured.',
        ]],
        ['Corner Cafe', 'Privacy', 'Licensed and insured.'], 'sm:footer-horizontal', 'business with location',
    ],
    'footer minimal' => [
        ['type' => 'footer', 'data' => [
            'variant' => 'minimal',
            'nav_links' => [['label' => 'Privacy', 'url' => '/privacy']],
            'note' => 'Licensed and insured.',
        ]],
        ['Corner Cafe', 'Privacy', 'Licensed and insured.'], 'footer-center', 'business with location',
    ],
]);

/**
 * The per-block views' conditional branches on partial data — the other side of
 * the happy paths above. Repeater state arrives either uuid-keyed (Filament) or
 * as a plain list (AI output), and individual items may be malformed.
 *
 * @param  array<int, string>  $see
 * @param  array<int, string>  $dontSee
 * @param  array<string, mixed>  $business
 */
it('renders optional fields and skips malformed repeater items per block view', function (array $block, array $see, array $dontSee, array $business): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();

    if ($business !== []) {
        $this->createTenantBusiness($tenant, $business, 0);
    }

    $this->createTenantPage($tenant, [$block]);

    $response = $this->get(sprintf('http://acme.%s/', $this->centralDomain()))->assertOk();

    foreach ($see as $text) {
        $response->assertSee($text);
    }

    foreach ($dontSee as $text) {
        $response->assertDontSee($text);
    }
})->with([
    'cta omits a link button when the url is missing' => [
        ['type' => 'cta', 'data' => [
            'variant' => 'banner',
            'heading' => 'Just a headline',
            'cta_label' => 'Label without url',
        ]],
        ['Just a headline'], ['Label without url'], [],
    ],
    // A paragraph that is missing, blank or not a string leaves no empty <p>
    // behind — an operator who clears an entry rather than deleting it must not
    // get a gap in the rhythm of the page.
    'prose skips empty and malformed paragraphs' => [
        ['type' => 'prose', 'data' => [
            'heading' => 'About us',
            'paragraphs' => [
                'a1b2-uuid' => ['text' => 'A real paragraph.'],
                'blank' => ['text' => '   '],
                'wrong-shape' => ['text' => ['nested' => 'no']],
                'not-an-item' => 'not an array',
            ],
        ]],
        ['A real paragraph.'], ['not an array'], [],
    ],
    // An ungrouped item renders with no heading above it, so a flat list of
    // items never grows a stray empty section title.
    'offerings renders ungrouped items without a group heading' => [
        ['type' => 'offerings', 'data' => [
            'variant' => 'list',
            'heading' => 'Services',
            'items' => [
                ['name' => 'Consultation', 'price' => 'Free'],
                ['group' => '   ', 'name' => 'Follow-up', 'price' => '$50'],
            ],
        ]],
        ['Consultation', 'Follow-up'], ['uppercase tracking-wider'], [],
    ],
    'faq skips a question with no text' => [
        ['type' => 'faq', 'data' => [
            'questions' => [
                'a1b2-uuid' => ['question' => 'A real question?', 'answer' => 'A real answer.'],
                'blank' => ['question' => '  ', 'answer' => 'Orphaned answer.'],
                'not-an-item' => 'not an array',
            ],
        ]],
        ['A real question?'], ['Orphaned answer.', 'not an array'], [],
    ],
    'features skips a malformed repeater item' => [
        ['type' => 'features', 'data' => [
            'variant' => 'grid',
            'features' => [
                'a1b2-uuid' => ['title' => 'From the panel'],
                'bad-item' => 'not an array',
            ],
        ]],
        ['From the panel'], ['not an array'], [],
    ],
    'gallery skips an image without a url' => [
        ['type' => 'gallery', 'data' => [
            'variant' => 'grid',
            'images' => [
                'a1b2-uuid' => ['url' => 'https://example.com/panel.jpg'],
                'no-url' => ['caption' => 'Orphan caption'],
            ],
        ]],
        ['https://example.com/panel.jpg'], ['Orphan caption'], [],
    ],
    'testimonials skips a malformed repeater item' => [
        ['type' => 'testimonials', 'data' => [
            'variant' => 'grid',
            'testimonials' => [
                'a1b2-uuid' => ['quote' => 'Panel-authored quote', 'author' => 'Cara'],
                'bad-item' => 'not an array',
            ],
        ]],
        ['Panel-authored quote', 'Cara'], ['not an array'], [],
    ],
    'header renders the business logo when one is set' => [
        ['type' => 'header', 'data' => ['variant' => 'simple']],
        ['logos/corner.png'], [], ['name' => 'Corner Cafe', 'logo_path' => 'logos/corner.png'],
    ],
]);

it('escapes authored block content to prevent stored XSS', function (): void {
    $payload = '<script>alert(1)</script>';
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => $payload]],
        ['type' => 'cta', 'data' => ['variant' => 'boxed', 'heading' => $payload, 'cta_label' => 'Go', 'cta_url' => '/x']],
        ['type' => 'features', 'data' => ['variant' => 'list', 'features' => [['title' => $payload]]]],
        ['type' => 'gallery', 'data' => ['variant' => 'grid', 'images' => [['url' => 'https://example.com/x.jpg', 'caption' => $payload]]]],
        ['type' => 'testimonials', 'data' => ['variant' => 'grid', 'testimonials' => [['quote' => $payload, 'author' => 'Eve']]]],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertDontSee($payload, false)
        ->assertSee('&lt;script&gt;', false);
});

it('strips executable url schemes out of every rendered href and src', function (): void {
    // Blade's {{ }} escapes the VALUE but not the SCHEME, so a stored
    // `javascript:` cta_url would render as a live link. Covers both nesting
    // levels (top-level props and repeater items) and both contexts (href, src).
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantBusiness($tenant, ['name' => 'Corner Cafe']);
    $this->createTenantPage($tenant, [
        ['type' => 'cta', 'data' => [
            'variant' => 'boxed',
            'heading' => 'Book now',
            'cta_label' => 'Go',
            'cta_url' => 'javascript:alert(1)',
            'secondary_label' => 'Later',
            'secondary_url' => "java\tscript:alert(2)",
        ]],
        ['type' => 'hero', 'data' => [
            'variant' => 'full-bleed-overlay',
            'heading' => 'Welcome',
            'image_url' => 'data:text/html;base64,PHNjcmlwdD4x',
        ]],
        ['type' => 'gallery', 'data' => ['variant' => 'grid', 'images' => [
            ['url' => 'JaVaScRiPt:alert(3)', 'alt' => 'Bad'],
            ['url' => 'https://example.com/good.jpg', 'alt' => 'Good'],
        ]]],
    ]);

    $response = $this->get(sprintf('http://acme.%s/', $this->centralDomain()));

    $response->assertOk()
        ->assertDontSee('javascript:', false)
        ->assertDontSee('vbscript:', false)
        ->assertDontSee('data:text/html', false)
        // The legitimate sibling still renders, so the guard is not blanket.
        ->assertSee('https://example.com/good.jpg', false)
        // Labels survive: only the url key is dropped, not the whole block.
        ->assertSee('Book now', false)
        ->assertSee('Welcome', false);
});

it('skips a bound block with a warning when its bind cannot resolve, while siblings render', function (array $block, string $dontSee, bool $withBusiness, int $warnings): void {
    Log::spy();

    $tenant = Tenant::factory()->withDomain('acme')->create();

    if ($withBusiness) {
        $this->createTenantBusiness($tenant, [], 0);
    }

    $this->createTenantPage($tenant, [
        $block,
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Still here']],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Still here')
        ->assertDontSee($dontSee);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $message === 'fabricator.block_skipped'
            && $context['reason'] === 'unresolved_bind'
            && $context['type'] === $block['type'])
        ->times($warnings);
})->with([
    'header without a business' => [
        ['type' => 'header', 'data' => ['variant' => 'simple']], 'navbar-end', false, 1,
    ],
    'contact without a business' => [
        ['type' => 'contact', 'data' => ['variant' => 'split', 'heading' => 'Ghost contact']], 'Ghost contact', false, 1,
    ],
    // The default footer chrome also fails to bind (business without
    // locations) but warns with its own type, so the filter keeps this at 1.
    'contact without locations' => [
        ['type' => 'contact', 'data' => ['variant' => 'split', 'heading' => 'Ghost contact']], 'Ghost contact', true, 1,
    ],
    // Here the failing type IS footer: the page's block and the default
    // footer chrome each warn.
    'footer without locations' => [
        ['type' => 'footer', 'data' => ['variant' => 'minimal']], 'footer-center', true, 2,
    ],
]);
