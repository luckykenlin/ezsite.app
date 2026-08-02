<?php

declare(strict_types=1);

use App\Ai\SiteChromeDraft;
use App\Enums\ChromeSlot;

/**
 * The turn-scoped header/footer draft — the third staging object, and the one
 * that finally lets the assistant answer "add Services to the menu".
 *
 * What matters here is the same distinction SiteStyleDraft draws: "left alone"
 * must be tellable from "set back to what it was", because a draft handed to the
 * editor marks the site chrome dirty and asks the operator to save something.
 */
function chromeDraft(): SiteChromeDraft
{
    return new SiteChromeDraft([
        'header' => ['type' => 'header', 'data' => [
            'variant' => 'simple',
            'nav_links' => [['label' => 'Home', 'url' => '/']],
        ]],
        'footer' => ['type' => 'footer', 'data' => ['variant' => 'minimal', 'note' => 'Open daily.']],
    ]);
}

it('reads a slot through to the saved entry until something stages over it', function (): void {
    $draft = chromeDraft();

    expect($draft->current(ChromeSlot::Header)['data']['variant'])->toBe('simple')
        // Nothing staged, so nothing for the editor to apply — the whole point,
        // since an unchanged draft would flag the chrome dirty for no reason.
        ->and($draft->toArray())->toBeNull();

    $draft->stage(ChromeSlot::Header, ['variant' => 'centered']);

    expect($draft->current(ChromeSlot::Header)['data'])->toBe(['variant' => 'centered'])
        ->and($draft->toArray())->toBe(['header' => ['type' => 'header', 'data' => ['variant' => 'centered']]]);
});

/*
 * Only CHANGED slots travel back. The editor merges per slot, so a turn that
 * edited the header must not also hand over a footer it never looked at — that
 * would overwrite a footer the operator was editing by hand while it ran.
 */
it('hands back only the slots the turn actually touched', function (): void {
    $draft = chromeDraft();

    $draft->stage(ChromeSlot::Footer, ['note' => 'Closed Sundays.']);

    expect(array_keys((array) $draft->toArray()))->toBe(['footer'])
        // The untouched header still reads through to what is saved.
        ->and($draft->current(ChromeSlot::Header)['data']['nav_links'])->toBe([['label' => 'Home', 'url' => '/']]);
});

it('falls back to an empty entry for a slot it was given nothing for', function (): void {
    // A defensive path, not a normal one: ChatEditPage only builds this draft
    // when a Business exists, and SiteChrome then always answers with an entry.
    $draft = new SiteChromeDraft([]);

    expect($draft->current(ChromeSlot::Header))->toBe(['type' => 'header', 'data' => []]);
});

it('always outlines both slots, with the reserved keys lifted out of the content', function (): void {
    $outline = chromeDraft()->outline();

    expect($outline)->toContain('- header (simple)')
        ->and($outline)->toContain('- footer (minimal)')
        // The model needs the links it already has: seeing the menu is what
        // stops add_links re-adding an existing link under a second label.
        ->and($outline)->toContain('"label":"Home"')
        // variant is lifted into the parenthesis, not left in the JSON, so the
        // model does not write it back as though it were content.
        ->and($outline)->not->toContain('"variant"');
});

it('says so plainly when a slot holds nothing at all', function (): void {
    $outline = new SiteChromeDraft([
        'header' => ['type' => 'header', 'data' => []],
        'footer' => ['type' => 'footer', 'data' => ['variant' => 'minimal']],
    ])->outline();

    expect($outline)->toContain('- header — nothing set')
        ->and($outline)->toContain('- footer (minimal) — nothing set');
});

it('truncates a long value in the outline rather than carrying a whole page of it', function (): void {
    $outline = new SiteChromeDraft([
        'header' => ['type' => 'header', 'data' => ['cta_label' => str_repeat('a', 200)]],
        'footer' => ['type' => 'footer', 'data' => []],
    ])->outline();

    expect($outline)->toContain('...')
        ->and(mb_strlen($outline))->toBeLessThan(300);
});
