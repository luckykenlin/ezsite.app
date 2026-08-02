<?php

declare(strict_types=1);

use App\Ai\PageDraft;

/*
 * The working copy the chat tools share for one turn. Its outline() is every
 * tool's return value, so the model re-reads real state after each edit — these
 * assert the parts the model depends on: addressable keys, positions, and the
 * reserved keys never leaking into content.
 */

function draftBlocks(): array
{
    return [
        ['key' => 'k1', 'type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
        ['key' => 'k2', 'type' => 'heading', 'data' => ['content' => 'About us', 'level' => 'h2']],
    ];
}

it('finds a block by key and returns null for an unknown one', function (): void {
    $draft = new PageDraft(draftBlocks());

    expect($draft->find('k2')['type'])->toBe('heading')
        ->and($draft->find('nope'))->toBeNull();
});

/*
 * locate() and reply() are the two primitives every block tool leans on, and
 * both existed only to stop something being repeated at ~25 call sites — so
 * they get a test under their own name rather than being re-derived from a
 * tool's behaviour. The five tools keep their own unknown-key tests: each one
 * carries its OWN `if (locate(...) === null) return reply(...)` branch, so
 * those are five production guards, not five restatements of one.
 */
it('tolerates a tool argument that is not a string, without narrowing at every call site', function (mixed $key, ?string $expectedType): void {
    expect(new PageDraft(draftBlocks())->locate($key)['type'] ?? null)->toBe($expectedType);
})->with([
    'a real key' => ['k1', 'hero'],
    'a hallucinated key' => ['nope', null],
    'the argument was omitted' => [null, null],
    'the model sent a number' => [42, null],
    'the model sent an array' => [['k1'], null],
]);

it('answers with what it did, then the page as it now stands', function (): void {
    // Every tool returns this shape so the model re-reads real state instead of
    // tracking it; the blank line between the two halves is prompt surface.
    expect(new PageDraft(draftBlocks())->reply('Shortened the heading.'))
        ->toStartWith("Shortened the heading.\n\n")
        ->toContain('0. hero (centered-minimal) [key: k1]');
});

it('hands the replaced blocks straight back to the next tool in the turn', function (): void {
    $draft = new PageDraft(draftBlocks());

    // What it was built from comes back verbatim, so a tool reading the draft
    // before any edit sees the real page rather than a normalised copy.
    expect($draft->blocks())->toBe(draftBlocks());

    $draft->replace([draftBlocks()[1]]);

    expect($draft->blocks())->toBe([draftBlocks()[1]])
        ->and($draft->find('k1'))->toBeNull();
});

it('outlines each block with its position, key, type and variant', function (): void {
    $outline = new PageDraft(draftBlocks())->outline();

    expect($outline)->toContain('0. hero (centered-minimal) [key: k1]')
        ->toContain('1. heading [key: k2]')
        ->toContain('"heading":"Welcome"');
});

it('keeps the server-owned reserved keys out of the outlined content', function (): void {
    $outline = new PageDraft([
        ['key' => 'k1', 'type' => 'contact', 'data' => [
            'variant' => 'grid',
            'bind' => ['location_id' => 7],
            'heading' => 'Visit us',
        ]],
    ])->outline();

    // The variant is shown as a label, never as an editable field, and the bind
    // target is not the model's business at all.
    expect($outline)->toContain('contact (grid)')
        ->and($outline)->not->toContain('location_id')
        ->and($outline)->not->toContain('"variant"');
});

it('marks a block that has no content yet', function (): void {
    expect(new PageDraft([['key' => 'k1', 'type' => 'gallery', 'data' => []]])->outline())
        ->toContain('— no content');
});

it('describes a structurally broken block as unknown rather than blank', function (): void {
    expect(new PageDraft([['key' => 'k1', 'type' => '', 'data' => []]])->outline())
        ->toContain('0. unknown [key: k1]');
});

it('truncates long prose so a whole page still fits in one outline', function (): void {
    $outline = new PageDraft([
        ['key' => 'k1', 'type' => 'cta', 'data' => ['body' => str_repeat('word ', 200)]],
    ])->outline();

    expect($outline)->toContain('...')
        ->and(mb_strlen($outline))->toBeLessThan(400);
});

it('says so when the page is empty', function (): void {
    expect(new PageDraft([])->outline())->toBe('The page is empty — it has no blocks.');
});
