<?php

declare(strict_types=1);

use App\Ai\PageDraft;
use App\Ai\Prompts\PageEditPrompt;
use App\Filament\Fabricator\BlockRegistry;
use App\Models\Business;
use App\Models\Page;

function editPrompt(PageDraft $draft, ?Business $business = null, string $message = 'Shorten the headline'): string
{
    $page = Page::factory()->make(['title' => 'Home', 'slug' => '/', 'status' => 'draft']);

    return (string) new PageEditPrompt($page, $draft, BlockRegistry::vocabulary(), $business, $message);
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

    expect((string) new PageEditPrompt($page, heroPageDraft(), BlockRegistry::vocabulary(), null, 'hi'))
        ->toContain('Status: published');
});

it('carries the current page outline so the model addresses real keys', function (): void {
    expect(editPrompt(heroPageDraft()))->toContain('[key: k1]');
});

/*
 * Only the types actually on the page get their full field list: the vocabulary
 * for every block type is a lot of tokens to spend on sections the operator did
 * not mention, and AddBlock's own schema enumerates the addable types anyway.
 */
it('lists the fields of the types on the page and only names the rest', function (): void {
    $prompt = editPrompt(heroPageDraft());

    expect($prompt)->toContain('- hero: eyebrow, heading, subheading')
        ->and($prompt)->toContain('Types you can add:')
        ->and($prompt)->toContain('cta')
        // A type not on the page must not bring its field list along.
        ->and($prompt)->not->toContain('- cta: heading, body');
});

it('flags a bound block so the model only writes its narrative fields', function (): void {
    $prompt = editPrompt(new PageDraft([
        ['key' => 'k1', 'type' => 'contact', 'data' => ['heading' => 'Visit us']],
    ]));

    expect($prompt)->toContain('- contact: heading, intro, show_form, success_message')
        ->toContain('shows live location details automatically');
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
