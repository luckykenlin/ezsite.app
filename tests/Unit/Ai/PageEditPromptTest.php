<?php

declare(strict_types=1);

use App\Ai\ChatAttachment;
use App\Ai\PageDraft;
use App\Ai\Prompts\PageEditPrompt;
use App\Ai\SiteStyleDraft;
use App\Design\StylePreset;
use App\Models\Business;
use App\Models\Page;
use App\Models\Tenant;
use App\Site\Blocks\BlockVocabulary;
use App\Site\SiteContext;

function editPrompt(PageDraft $draft, ?Business $business = null, string $message = 'Shorten the headline'): string
{
    $page = Page::factory()->make(['title' => 'Home', 'slug' => '/', 'status' => 'draft']);

    return (string) new PageEditPrompt($page, $draft, resolve(BlockVocabulary::class)->all(), $business, $message);
}

function heroPageDraft(): PageDraft
{
    return new PageDraft([
        ['key' => 'k1', 'type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);
}

it('states which page is open and whether it is live', function (): void {
    expect(editPrompt(heroPageDraft()))
        ->toContain('Title: Home')
        ->toContain('draft (not visible to the public yet)');
});

it('marks a published page as published', function (): void {
    $page = Page::factory()->make(['title' => 'Home', 'slug' => '/', 'status' => 'published']);

    expect((string) new PageEditPrompt($page, heroPageDraft(), resolve(BlockVocabulary::class)->all(), null, 'hi'))
        ->toContain('Status: published');
});

it('carries the current page outline so the model addresses real keys', function (): void {
    expect(editPrompt(heroPageDraft()))->toContain('[key: k1]');
});

/*
 * The canvas selection rides with the turn: "make it shorter" must mean the
 * block the operator is looking at, not a guess across ten blocks.
 */
it('names the selected section as the referent for unaddressed requests', function (): void {
    $page = Page::factory()->make(['title' => 'Home', 'slug' => '/', 'status' => 'draft']);

    $prompt = (string) new PageEditPrompt(
        $page,
        heroPageDraft(),
        resolve(BlockVocabulary::class)->all(),
        null,
        'Make it shorter',
        selectedBlockKey: 'k1',
    );

    expect($prompt)->toContain('## The selected section')
        ->toContain('the hero section selected on the canvas — key k1');
});

it('drops a selection that no longer resolves to a block', function (?string $key): void {
    // A key deleted mid-session (or no selection at all) must vanish rather
    // than point the model at a block the outline does not contain.
    $page = Page::factory()->make(['title' => 'Home', 'slug' => '/', 'status' => 'draft']);

    $prompt = (string) new PageEditPrompt(
        $page,
        heroPageDraft(),
        resolve(BlockVocabulary::class)->all(),
        null,
        'Make it shorter',
        selectedBlockKey: $key,
    );

    expect($prompt)->not->toContain('## The selected section');
})->with(['a deleted block' => 'gone', 'no selection' => null]);

/*
 * Only the types actually on the page get their full field list: the vocabulary
 * for every block type is a lot of tokens to spend on sections the operator did
 * not mention, and AddBlock seeds sample content anyway, so the model reads a new
 * block's real shape off the outline a moment after adding it.
 */
it('lists the fields of the types on the page and only the purpose of the rest', function (): void {
    $prompt = editPrompt(heroPageDraft());

    expect($prompt)->toContain('fields: eyebrow, heading, subheading')
        ->and($prompt)->toContain('Sections you can add:')
        // A type not on the page brings its purpose, never its field list.
        ->and($prompt)->toContain('- cta — One clear next step')
        ->and($prompt)->not->toContain('fields: heading, body');
});

/*
 * Purpose, not just name. Several containers present as "a heading plus a
 * repeater of titled items", so a bare name leaves the model choosing between
 * them by vibe — and the wrong container is a mistake no later edit fixes,
 * because the fields differ.
 */
it('says what each addable section is for', function (): void {
    $prompt = editPrompt(heroPageDraft());

    expect($prompt)->toContain('- prose — One or more paragraphs of body text')
        // Where two blocks are easily confused, the description names the
        // neighbour rather than leaving the model to infer the boundary.
        ->and($prompt)->toContain('for a paragraph, use prose');
});

it('flags a bound block so the model only writes its narrative fields', function (): void {
    $prompt = editPrompt(new PageDraft([
        ['key' => 'k1', 'type' => 'contact', 'data' => ['heading' => 'Visit us']],
    ]));

    expect($prompt)->toContain('fields: heading, intro, show_hours, show_form, success_message')
        ->toContain('shows live location details automatically');
});

/*
 * The announcement is what makes attachments usable: the bytes travel as
 * conversation-history attachments, so this section is the model's only source
 * for WHICH media id an image became and what each file is called.
 */
it('announces this message attachments right above the request', function (): void {
    $page = Page::factory()->make(['title' => 'Home', 'slug' => '/', 'status' => 'draft']);

    $prompt = (string) new PageEditPrompt(
        $page,
        heroPageDraft(),
        resolve(BlockVocabulary::class)->all(),
        null,
        'Use this as the hero image',
        attachments: [
            new ChatAttachment('image', 'kitchen.jpg', [], mediaId: 42, width: 1600, height: 900),
            new ChatAttachment('document', 'menu.pdf', []),
        ],
    );

    expect($prompt)->toContain('## Attachments on this message')
        ->toContain("image 'kitchen.jpg' (1600x900) — already imported into the media library as media id 42")
        ->toContain("document 'menu.pdf'")
        // Above the request, because the request is usually about the files.
        ->and(mb_strpos($prompt, '## Attachments'))->toBeLessThan(mb_strpos($prompt, 'Use this as the hero image'));
});

it('omits the attachments section on a plain text message', function (): void {
    expect(editPrompt(heroPageDraft()))->not->toContain('## Attachments');
});

it('says the page is empty when it has no blocks', function (): void {
    expect(editPrompt(new PageDraft([])))->toContain('(nothing on the page yet)');
});

it('skips a block whose stored type is no longer registered', function (): void {
    $prompt = editPrompt(new PageDraft([['key' => 'k1', 'type' => 'ghost', 'data' => []]]));

    expect($prompt)->toContain('(nothing on the page yet)')
        ->and($prompt)->not->toContain('- ghost');
});

it('passes the business facts and the output language', function (): void {
    // make(), not create(): the prompt only reads attributes, so there is no
    // reason for this to touch the database or tenant context at all.
    $business = Business::factory()->make([
        'name' => 'Corner Cafe',
        'category' => 'Bakery',
        'tagline' => 'Fresh daily',
        'description' => 'A neighborhood bakery.',
        'contact_email' => 'hi@corner.test',
        'contact_phone' => '555-0100',
        'locale' => 'zh',
    ]);

    $prompt = editPrompt(heroPageDraft(), $business);

    expect($prompt)->toContain('These are the only facts you may state.')
        ->toContain('Name: Corner Cafe')
        ->toContain('Category: Bakery')
        ->toContain('Tagline: Fresh daily')
        ->toContain('Description: A neighborhood bakery.')
        ->toContain('Email: hi@corner.test')
        ->toContain('Phone: 555-0100')
        ->toContain('Write user-visible copy in: zh');
});

it('omits absent business details and defaults the language', function (): void {
    $business = Business::factory()->make([
        'name' => 'Corner Cafe',
        'category' => null,
        'tagline' => null,
        'description' => null,
        'contact_email' => null,
        'contact_phone' => null,
        'locale' => null,
    ]);

    $prompt = editPrompt(heroPageDraft(), $business);

    expect($prompt)->toContain('Name: Corner Cafe')
        ->toContain('Write user-visible copy in: en')
        ->and($prompt)->not->toContain('Category:')
        ->and($prompt)->not->toContain('Phone:');
});

/*
 * Without a profile the assistant has no facts at all, so the "never invent"
 * rule has to be stated as the whole of the section rather than implied.
 */
it('forbids stating facts when there is no business profile', function (): void {
    expect(editPrompt(heroPageDraft()))
        ->toContain('None recorded. Do not state any specific facts about the business.');
});

it('ends with the operator request', function (): void {
    expect(editPrompt(heroPageDraft(), null, 'Make it shorter'))
        ->toEndWith("## The operator's request\nMake it shorter");
});

/*
 * The layouts each present type offers, inline rather than behind a lookup tool.
 * SetBlockVariant validates the choice, but the model can only make a sensible
 * one if it knows the options; ~5 tokens per type actually on the page is a
 * cheaper way to say so than a round trip.
 */
it('lists the layouts the types on this page can switch to', function (): void {
    expect(editPrompt(heroPageDraft()))
        ->toContain('layouts: centered-minimal, left-text-right-image, full-bleed-overlay');
});

/*
 * The style MENU appears only alongside the tool that can act on it — tokens
 * live on the Business row, so with no profile there is nowhere for a style to
 * land. But the section itself stays: silence left the model improvising
 * excuses for a verb it mysteriously lacked, where one line lets it say why a
 * style request cannot be served yet.
 */
it('says plainly why the style cannot change when there is no business profile', function (): void {
    $prompt = editPrompt(heroPageDraft());

    expect($prompt)->toContain('## Site style')
        ->toContain('cannot be changed until the business profile is set up')
        ->not->toContain('The styles you can choose from');
});

it('publishes the style menu and where the site currently stands', function (): void {
    $tenant = Tenant::factory()->create();
    $page = $this->createTenantPage($tenant, []);

    $prompt = (string) new PageEditPrompt(
        $page,
        heroPageDraft(),
        resolve(BlockVocabulary::class)->all(),
        null,
        'make it premium',
        null,
        new SiteStyleDraft(StylePreset::CalmCoastal->tokens()),
    );

    expect($prompt)->toContain('## Site style')
        ->toContain('currently uses: calm-coastal')
        ->toContain('Changing this affects every page')
        // The adjectives are the grounding for "premium"; without them the model
        // is choosing among six opaque slugs.
        ->toContain('premium')
        // A named brand is translated into those words, never stored or echoed.
        ->toContain('never stored or repeated back')
        // The colour grounding: "deep blue" has to land on a palette slug, and
        // the slugs alone don't say which. A named colour is a fine-tune, so
        // the model must not answer it by switching the whole style.
        ->toContain('deep blue')
        ->toContain('is a palette fine-tune, NOT a style change');
});

it('tells the model which page addresses actually exist', function (): void {
    $tenant = Tenant::factory()->create();
    $page = $this->createTenantPage($tenant, []);
    $this->createTenantPage($tenant, [], '/contact');

    $prompt = (string) new PageEditPrompt(
        $page,
        heroPageDraft(),
        resolve(BlockVocabulary::class)->all(),
        null,
        'link to the contact page',
        new SiteContext,
    );

    expect($prompt)->toContain('## This site')
        ->toContain('/contact (published)');
});

/*
 * A page can legitimately use every section type there is. Offering an empty
 * "Sections you can add:" heading would be a line of prompt that says nothing
 * and invites an AddBlock call with no legal argument.
 *
 * The draft is built from the vocabulary rather than a hand-written list of
 * types, so a new block type keeps this honest instead of quietly making the
 * page no-longer-complete.
 */
it('offers nothing to add when the page already uses every section type', function (): void {
    $blocks = [];

    foreach (resolve(BlockVocabulary::class)->pageTypeNames() as $index => $type) {
        $blocks[] = ['key' => 'k'.$index, 'type' => $type, 'data' => []];
    }

    expect(editPrompt(new PageDraft($blocks)))->not->toContain('Sections you can add');
});

it('lists the layout axes of the types on the page, names only', function (): void {
    $prompt = editPrompt(heroPageDraft());

    // Names only — the values and their guidance live once, in the restyle
    // tool's schema, not in every prompt.
    expect($prompt)->toContain('axes: tone, spacing, width, align, image_shape')
        ->toContain('"Two columns", "left-align the heading", "drop the cards" are axis changes')
        ->not->toContain('narrow is');
});
