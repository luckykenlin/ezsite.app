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
    'hero full-viewport-quiet' => [
        ['type' => 'hero', 'data' => ['variant' => 'full-viewport-quiet', 'heading' => 'Welcome friends']],
        ['Welcome friends'], 'min-h-[85svh]', 'none',
    ],
    // The same variant given a photograph: the viewport-filling height is what
    // gives way, because a full-height section with an image below the fold
    // reads as an accident.
    'hero full-viewport-quiet with an image' => [
        ['type' => 'hero', 'data' => ['variant' => 'full-viewport-quiet', 'heading' => 'Welcome friends', 'image_url' => 'https://example.test/room.jpg']],
        ['Welcome friends'], 'room.jpg', 'none',
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
        ['Ready to book?', 'Book now', 'Call us'], 'site-tone-accent', 'none',
    ],
    'cta banner on a card (the old boxed)' => [
        ['type' => 'cta', 'data' => [
            'variant' => 'banner',
            'appearance' => ['item_style' => 'card'],
            'heading' => 'Ready to book?',
            'cta_label' => 'Book now',
            'cta_url' => '/contact',
            'secondary_label' => 'Call us',
            'secondary_url' => 'tel:+15551234567',
        ]],
        ['Ready to book?', 'Book now', 'Call us'], 'site-card-body', 'none',
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
    'features as a single-column list (the old list variant)' => [
        ['type' => 'features', 'data' => [
            'variant' => 'grid',
            'appearance' => ['columns' => 'one', 'item_style' => 'plain', 'width' => 'narrow', 'align' => 'start'],
            'heading' => 'Why choose us',
            'features' => [['title' => 'Fast turnaround', 'description' => 'Same-day service']],
        ]],
        ['Why choose us', 'Fast turnaround'], 'grid-cols-1', 'none',
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
    // Two items, because the layout's whole point is positional: even rows put
    // the image column second, and only a second row makes that class render.
    'features alternating' => [
        ['type' => 'features', 'data' => [
            'variant' => 'alternating',
            'heading' => 'Why choose us',
            'features' => [
                ['icon' => '⭐', 'title' => 'Fast turnaround', 'description' => 'Same-day service'],
                ['title' => 'Award winning', 'image_url' => 'https://example.com/award.jpg'],
            ],
        ]],
        ['Why choose us', 'Fast turnaround', 'Award winning'], 'md:order-2', 'none',
    ],
    'features rows' => [
        ['type' => 'features', 'data' => [
            'variant' => 'rows',
            'heading' => 'Why choose us',
            'features' => [['title' => 'Fast turnaround', 'description' => 'Same-day service']],
        ]],
        ['Why choose us', 'Fast turnaround'], 'site-item-rule-lead', 'none',
    ],
    // No image on purpose: the backdrop is guarded, so the centered column must
    // stand on its own over the plain inverted section.
    'cta full-photo' => [
        ['type' => 'cta', 'data' => [
            'variant' => 'full-photo',
            'heading' => 'Ready to book?',
            'body' => 'Slots fill up fast.',
            'cta_label' => 'Book now',
            'cta_url' => '/contact',
            'secondary_label' => 'Call us',
            'secondary_url' => 'tel:+15551234567',
        ]],
        ['Ready to book?', 'Book now', 'Call us'], 'max-w-3xl', 'none',
    ],
    'features list with an item image and link' => [
        ['type' => 'features', 'data' => [
            'variant' => 'grid',
            'appearance' => ['columns' => 'one', 'item_style' => 'plain'],
            'features' => [[
                'title' => 'Deep clean',
                'image_url' => 'https://example.com/clean.jpg',
                'link_url' => '/services/deep-clean',
            ]],
        ]],
        ['Deep clean', 'https://example.com/clean.jpg'], 'after:absolute', 'none',
    ],
    // prose and faq each gained a second COMPOSITION (not a second style): the
    // heading beside the text, and questions in two columns. Both keep every
    // word visible — an accordion is ruled out for faq at any variant, because
    // the editor canvas intercepts clicks and <details> would never open.
    'prose stacked' => [
        ['type' => 'prose', 'data' => [
            'variant' => 'stacked',
            'heading' => 'About us',
            'paragraphs' => [['text' => 'We opened in 2010.']],
        ]],
        ['About us', 'We opened in 2010.'], 'max-w-3xl', 'none',
    ],
    'prose side-heading' => [
        ['type' => 'prose', 'data' => [
            'variant' => 'side-heading',
            'heading' => 'About us',
            'paragraphs' => [['text' => 'We opened in 2010.']],
        ]],
        ['About us', 'We opened in 2010.'], 'md:sticky', 'none',
    ],
    'faq list' => [
        ['type' => 'faq', 'data' => [
            'heading' => 'Questions',
            'questions' => [['question' => 'Do you deliver?', 'answer' => 'Within five miles.']],
        ]],
        ['Questions', 'Do you deliver?', 'Within five miles.'], 'divide-y', 'none',
    ],
    'faq in two columns (the old grid variant)' => [
        ['type' => 'faq', 'data' => [
            'appearance' => ['columns' => 'two', 'width' => 'wide', 'align' => 'center'],
            'heading' => 'Questions',
            'questions' => [['question' => 'Do you deliver?', 'answer' => 'Within five miles.']],
        ]],
        ['Questions', 'Do you deliver?', 'Within five miles.'], 'sm:grid-cols-2', 'none',
    ],
    // The five types added to widen what the assistant can reach for. Each is a
    // shape the library genuinely lacked, not a variation on one it had — see
    // each block class's description for the line that separates it from its
    // nearest look-alike.
    'steps numbers itself from position, not from content' => [
        ['type' => 'steps', 'data' => [
            'heading' => 'How it works',
            'steps' => [
                ['title' => 'Get in touch', 'description' => 'Call or email.'],
                ['title' => 'We visit', 'description' => 'Free quote.'],
            ],
        ]],
        ['How it works', 'Get in touch', 'We visit'], '<ol', 'none',
    ],
    'steps timeline' => [
        ['type' => 'steps', 'data' => [
            'variant' => 'timeline',
            'heading' => 'How it works',
            'steps' => [
                ['title' => 'Get in touch', 'description' => 'Call or email.'],
                ['title' => 'We visit', 'description' => 'Free quote.'],
            ],
        ]],
        ['How it works', 'Get in touch', 'We visit'], 'border-l-2', 'none',
    ],
    'stats pairs each number with what it counts' => [
        ['type' => 'stats', 'data' => [
            'heading' => 'By the numbers',
            'stats' => [['value' => '500+', 'label' => 'happy customers', 'description' => 'since 2010']],
        ]],
        ['500+', 'happy customers', 'since 2010'], '<dl', 'none',
    ],
    'stats band (the old variant, via the tone axis)' => [
        ['type' => 'stats', 'data' => [
            'appearance' => ['tone' => 'inverted'],
            'heading' => 'By the numbers',
            'stats' => [['value' => '500+', 'label' => 'happy customers']],
        ]],
        ['500+', 'happy customers'], 'site-stat text-primary', 'none',
    ],
    'team renders a person with a role' => [
        ['type' => 'team', 'data' => [
            'heading' => 'Meet the team',
            'members' => [['name' => 'Dana Reed', 'role' => 'Head baker', 'bio' => 'Twelve years at the oven.']],
        ]],
        ['Meet the team', 'Dana Reed', 'Head baker', 'Twelve years at the oven.'], 'rounded-full', 'none',
    ],
    'team tiles (the old variant, via card + portrait axes)' => [
        ['type' => 'team', 'data' => [
            'appearance' => ['item_style' => 'card', 'image_shape' => 'portrait'],
            'heading' => 'Meet the team',
            'members' => [['name' => 'Dana Reed', 'role' => 'Head baker', 'bio' => 'Twelve years at the oven.']],
        ]],
        ['Meet the team', 'Dana Reed', 'Head baker', 'Twelve years at the oven.'], 'aspect-[4/5]', 'none',
    ],
    'logos uses the brand name as alt text' => [
        ['type' => 'logos', 'data' => [
            'heading' => 'Trusted by',
            'logos' => [['url' => 'https://example.com/acme.png', 'name' => 'Acme Corp']],
        ]],
        ['Trusted by', 'Acme Corp', 'https://example.com/acme.png'], 'grayscale', 'none',
    ],
    'pricing renders a plan with its included lines' => [
        ['type' => 'pricing', 'data' => [
            'heading' => 'Choose a plan',
            'plans' => [[
                'name' => 'Standard',
                'price' => '$29',
                'period' => 'per month',
                'features' => "Weekly visit\nSame-day callout",
                'cta_label' => 'Start now',
                'cta_url' => '/contact',
            ]],
        ]],
        ['Choose a plan', 'Standard', '$29', 'per month', 'Weekly visit', 'Same-day callout', 'Start now'], 'card-actions', 'none',
    ],
    'pricing highlights the featured plan' => [
        ['type' => 'pricing', 'data' => [
            'heading' => 'Choose a plan',
            'plans' => [
                ['name' => 'Starter', 'price' => '$19', 'cta_label' => 'Start now', 'cta_url' => '/contact'],
                ['name' => 'Standard', 'price' => '$29', 'is_featured' => true, 'cta_label' => 'Start now', 'cta_url' => '/contact'],
            ],
        ]],
        ['Choose a plan', 'Starter', 'Standard', '$29', 'Recommended'], 'ring-primary', 'none',
    ],
    // One block for menus, service lists and packages alike — what differs
    // between those is the content, not the layout.
    // The practical strip. Its open-or-closed line is derived, not authored,
    // so the marker is the lit dot rather than a phrase from the block data —
    // OpeningStateTest pins the wording against a frozen clock.
    'visit' => [
        ['type' => 'visit', 'data' => [
            'heading' => 'Come and find us',
            'intro' => 'Walk in, or call ahead.',
            'note' => 'Street parking after seven.',
        ]],
        ['Come and find us', 'Street parking after seven.'], 'site-open-state', 'business with location',
    ],
    'offerings as a priced list (the old list variant)' => [
        ['type' => 'offerings', 'data' => [
            'appearance' => ['columns' => 'one', 'item_style' => 'plain', 'width' => 'narrow', 'align' => 'start'],
            'heading' => 'Our menu',
            'items' => [
                ['group' => 'Starters', 'name' => 'Bruschetta', 'price' => '$9', 'description' => 'Tomato, basil, garlic.'],
                ['group' => 'Mains', 'name' => 'Margherita', 'price' => '$18'],
            ],
        ]],
        ['Our menu', 'Starters', 'Bruschetta', '$9', 'Mains', 'Margherita'], 'site-menu-price', 'none',
    ],
    'offerings cards' => [
        ['type' => 'offerings', 'data' => [
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
        ['Our work', 'https://example.com/one.jpg', 'Signature look'], 'lg:grid-cols-3', 'none',
    ],
    'gallery masonry' => [
        ['type' => 'gallery', 'data' => [
            'variant' => 'masonry',
            'heading' => 'Our work',
            'images' => [['url' => 'https://example.com/one.jpg', 'caption' => 'Signature look']],
        ]],
        ['Our work', 'https://example.com/one.jpg', 'Signature look'], 'columns-2', 'none',
    ],
    'gallery filmstrip' => [
        ['type' => 'gallery', 'data' => [
            'variant' => 'filmstrip',
            'heading' => 'Our work',
            'images' => [['url' => 'https://example.com/one.jpg', 'caption' => 'Signature look']],
        ]],
        ['Our work', 'https://example.com/one.jpg', 'Signature look'], 'snap-x', 'none',
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
        ['What clients say', 'Absolutely wonderful service', 'Amy Chen', 'Ben Wu'], 'sm:grid-cols-2', 'none',
    ],
    'testimonials carousel' => [
        ['type' => 'testimonials', 'data' => [
            'variant' => 'carousel',
            'heading' => 'What clients say',
            'testimonials' => [['quote' => 'Absolutely wonderful service', 'author' => 'Amy Chen']],
        ]],
        ['What clients say', 'Absolutely wonderful service', 'Amy Chen'], 'carousel-item', 'none',
    ],
    // Two quotes stored, but the spotlight renders only the first — one voice
    // at full volume is the layout's whole idea.
    'testimonials spotlight' => [
        ['type' => 'testimonials', 'data' => [
            'variant' => 'spotlight',
            'heading' => 'What clients say',
            'testimonials' => [
                ['quote' => 'Absolutely wonderful service', 'author' => 'Amy Chen', 'role' => 'Regular'],
                ['quote' => 'Best in town', 'author' => 'Ben Wu'],
            ],
        ]],
        ['What clients say', 'Absolutely wonderful service', 'Amy Chen'], 'not-italic', 'none',
    ],
    'header simple' => [
        ['type' => 'header', 'data' => [
            'variant' => 'simple',
            'nav_links' => [['label' => 'About', 'url' => '/about'], ['label' => 'Menu', 'url' => '/menu']],
            'cta_label' => 'Book a table',
            'cta_url' => '/contact',
        ]],
        ['Corner Cafe', 'About', 'Menu', 'Book a table'], 'site-nav-links', 'business',
    ],
    'header centered' => [
        ['type' => 'header', 'data' => [
            'variant' => 'centered',
            'nav_links' => [['label' => 'About', 'url' => '/about']],
            'cta_label' => 'Book a table',
            'cta_url' => '/contact',
        ]],
        ['Corner Cafe', 'About', 'Book a table'], 'site-nav-stack', 'business',
    ],
    'footer columns' => [
        ['type' => 'footer', 'data' => [
            'variant' => 'columns',
            'nav_links' => [['label' => 'Privacy', 'url' => '/privacy']],
            'note' => 'Licensed and insured.',
        ]],
        ['Corner Cafe', 'Privacy', 'Licensed and insured.'], 'site-footer-columns', 'business with location',
    ],
    'footer minimal' => [
        ['type' => 'footer', 'data' => [
            'variant' => 'minimal',
            'nav_links' => [['label' => 'Privacy', 'url' => '/privacy']],
            'note' => 'Licensed and insured.',
        ]],
        ['Corner Cafe', 'Privacy', 'Licensed and insured.'], 'site-footer-center', 'business with location',
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
            'appearance' => ['columns' => 'one', 'item_style' => 'plain'],
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
        ['type' => 'cta', 'data' => ['variant' => 'banner', 'appearance' => ['item_style' => 'card'], 'heading' => $payload, 'cta_label' => 'Go', 'cta_url' => '/x']],
        ['type' => 'features', 'data' => ['variant' => 'grid', 'appearance' => ['columns' => 'one', 'item_style' => 'plain'], 'features' => [['title' => $payload]]]],
        ['type' => 'gallery', 'data' => ['variant' => 'grid', 'images' => [['url' => 'https://example.com/x.jpg', 'caption' => $payload]]]],
        ['type' => 'testimonials', 'data' => ['variant' => 'grid', 'testimonials' => [['quote' => $payload, 'author' => 'Eve']]]],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertDontSee($payload, false)
        ->assertSee('&lt;script&gt;', false);
});

it('escapes authored block content exactly once, whatever the prop is named', function (): void {
    // A snake_case prop used to come out double-escaped: rendering the block
    // through `<x-dynamic-component :attributes="...">` camel-cased the bag's
    // keys into view data, so `@props(['cta_label'])` fell back to the bag's
    // already-escaped copy and `{{ }}` escaped it a second time. "See
    // tonight's menu" shipped to a live demo site as "See tonight&#039;s menu".
    // `heading` (one word, so the camel-cased key still matched) was fine,
    // which is why this went unnoticed — both are asserted here.
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => [
            'variant' => 'centered-minimal',
            'heading' => "Tonight's specials & more",
            'cta_label' => "See tonight's menu",
            'cta_url' => '/menu',
        ]],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('See tonight&#039;s menu', false)
        ->assertSee('Tonight&#039;s specials &amp; more', false)
        ->assertDontSee('&amp;#039;', false);
});

it('strips executable url schemes out of every rendered href and src', function (): void {
    // Blade's {{ }} escapes the VALUE but not the SCHEME, so a stored
    // `javascript:` cta_url would render as a live link. Covers both nesting
    // levels (top-level props and repeater items) and both contexts (href, src).
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantBusiness($tenant, ['name' => 'Corner Cafe']);
    $this->createTenantPage($tenant, [
        ['type' => 'cta', 'data' => [
            'variant' => 'banner',
            'appearance' => ['item_style' => 'card'],
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
        ['type' => 'header', 'data' => ['variant' => 'simple']], 'site-nav-links', false, 1,
    ],
    'contact without a business' => [
        ['type' => 'contact', 'data' => ['heading' => 'Ghost contact']], 'Ghost contact', false, 1,
    ],
    // The default footer chrome also fails to bind (business without
    // locations) but warns with its own type, so the filter keeps this at 1.
    'contact without locations' => [
        ['type' => 'contact', 'data' => ['heading' => 'Ghost contact']], 'Ghost contact', true, 1,
    ],
    'visit without locations' => [
        ['type' => 'visit', 'data' => ['heading' => 'Ghost visit']], 'Ghost visit', true, 1,
    ],
    // Here the failing type IS footer: the page's block and the default
    // footer chrome each warn.
    'footer without locations' => [
        ['type' => 'footer', 'data' => ['variant' => 'minimal']], 'site-footer-center', true, 2,
    ],
]);

/*
 * The craft layer (resources/css/site.css) reaches the page through exactly
 * these hooks — a scrim that replaced the flat overlay, the enum-injected
 * card chrome, the offerings price leader, the hung testimonial quote, and
 * the arrow-link secondary CTA. One page exercises all of them so a view
 * refactor cannot silently drop a detail back to the DaisyUI default.
 */
it('renders the craft details through the shared site-* classes', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();

    $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => [
            'variant' => 'full-bleed-overlay',
            'heading' => 'Welcome friends',
            'image_url' => 'https://example.com/hero.jpg',
        ]],
        ['type' => 'hero', 'data' => [
            'variant' => 'left-text-right-image',
            'heading' => 'Meet the bakery',
            'image_url' => 'https://example.com/side.jpg',
        ]],
        ['type' => 'features', 'data' => [
            'variant' => 'grid',
            'features' => [['title' => 'Fast turnaround']],
        ]],
        ['type' => 'offerings', 'data' => [
            'appearance' => ['columns' => 'one', 'item_style' => 'plain'],
            'items' => [['name' => 'Espresso', 'price' => '$4']],
        ]],
        ['type' => 'testimonials', 'data' => [
            'variant' => 'grid',
            'testimonials' => [['quote' => 'Absolutely wonderful service', 'author' => 'Amy Chen']],
        ]],
        ['type' => 'cta', 'data' => [
            'variant' => 'banner',
            'heading' => 'Ready to book?',
            'cta_label' => 'Book now',
            'cta_url' => '/contact',
            'secondary_label' => 'Call us',
            'secondary_url' => 'tel:+15551234567',
        ]],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSeeHtml('site-scrim')
        ->assertDontSeeHtml('bg-neutral/60')
        ->assertSeeHtml('site-frame')
        ->assertSeeHtml('site-card')
        ->assertSeeHtml('site-leaders')
        ->assertSeeHtml('site-quote')
        ->assertSeeHtml('site-link-cta');
});

/*
 * Double-click-to-edit on the editor canvas resolves what it clicked through
 * these annotations. A repeater item is the case that cannot be resolved any
 * other way: the editor has to be told WHICH item, and the position it is
 * given here is what gets translated into the uuid Filament keys the draft by
 * (resources/js/page-editor/draft-fields.ts).
 */
it('annotates repeater item text with the draft path the inline editor writes to', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();

    $this->createTenantPage($tenant, [
        ['type' => 'features', 'data' => ['variant' => 'grid', 'heading' => 'Why us', 'features' => [
            ['title' => 'Fast turnaround', 'description' => 'Same-day, most days.'],
            ['title' => 'Fair pricing', 'description' => 'No surprises.'],
        ]]],
        ['type' => 'faq', 'data' => ['questions' => [
            ['question' => 'Do you deliver?', 'answer' => 'Within five miles.'],
        ]]],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        // The shared section header names the two fields every block spells
        // the same way...
        ->assertSeeHtml('data-editor-field="heading"')
        // ...and each item is addressed by its position in the repeater.
        ->assertSeeHtml('data-editor-field="features.0.title"')
        ->assertSeeHtml('data-editor-field="features.1.description"')
        ->assertSeeHtml('data-editor-field="questions.0.question"');
});
