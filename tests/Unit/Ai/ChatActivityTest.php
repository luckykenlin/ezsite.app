<?php

declare(strict_types=1);

use App\Ai\ChatActivity;
use App\Ai\PageDraft;

/*
 * The progress lines the chat rail shows while a turn works. They exist because
 * this agent edits through tools and writes its prose LAST: a multi-block rewrite
 * streams no text for most of a minute, and the operator had no way to tell that
 * from a hung turn.
 */

function chatActivityDraft(): PageDraft
{
    return new PageDraft([
        ['key' => 'k1', 'type' => 'hero', 'data' => ['heading' => 'Welcome']],
        ['key' => 'k2', 'type' => 'contact_form', 'data' => []],
    ]);
}

function activityLine(string $tool, array $arguments = []): ?string
{
    return resolve(ChatActivity::class)->forToolCall(chatActivityDraft(), $tool, $arguments);
}

it('names the block a tool call is about', function (string $tool, array $arguments, string $expected): void {
    expect(activityLine($tool, $arguments))->toBe($expected);
})->with([
    // The same Str::headline name the inspector and the canvas label it with, so
    // a progress line and the page agree about what a block is called.
    'rewriting' => ['UpdateBlockContent', ['key' => 'k1', 'content' => ['heading' => 'Fresh']], 'Rewriting the Hero block…'],
    'a multi-word type' => ['UpdateBlockContent', ['key' => 'k2', 'content' => []], 'Rewriting the Contact Form block…'],
    'removing' => ['RemoveBlock', ['key' => 'k1'], 'Removing the Hero block…'],
    // From the argument, not the draft: the block does not exist yet.
    'adding' => ['AddBlock', ['type' => 'cta'], 'Adding a Cta block…'],
    'reordering' => ['ReorderBlocks', ['keys' => ['k2', 'k1']], 'Reordering the page…'],
    'relaying one section' => ['SetBlockVariant', ['key' => 'k1', 'variant' => 'full-bleed-overlay'], 'Changing the Hero layout…'],
    // Named without the preset it is switching to: the argument is a slug the
    // operator has never seen, and the canvas is about to show them the answer.
    'restyling the site' => ['SetSiteStyle', ['preset' => 'warm-craft'], 'Restyling the site…'],
    'restyling one section' => ['SetBlockAppearance', ['key' => 'k1', 'tone' => 'inverted'], 'Restyling the Hero section…'],
]);

it('falls back to the indefinite line when the arguments name no block', function (string $tool, array $arguments, string $expected): void {
    // The arguments are the MODEL's, so a hallucinated key or a missing one is an
    // ordinary case. Naming a block that is not there would be worse than saying
    // less.
    expect(activityLine($tool, $arguments))->toBe($expected);
})->with([
    'an invented key' => ['UpdateBlockContent', ['key' => 'nope'], 'Rewriting a block…'],
    'no key at all' => ['UpdateBlockContent', [], 'Rewriting a block…'],
    'a non-string key' => ['RemoveBlock', ['key' => 42], 'Removing a block…'],
    'no type' => ['AddBlock', [], 'Adding a block…'],
    'a layout switch on an invented key' => ['SetBlockVariant', ['key' => 'nope'], 'Changing a block layout…'],
    'a restyle on an invented key' => ['SetBlockAppearance', ['key' => 'nope'], 'Restyling a section…'],
]);

it('says nothing at all about a tool it does not know', function (): void {
    // A generic "working…" would be a made-up line replacing an honest silence —
    // and the caller only streams what this returns.
    expect(activityLine('SomeFutureTool', ['key' => 'k1']))->toBeNull();
});

/*
 * Named by SLOT, because "editing the site" would not tell the operator that
 * their navigation — the thing on every page — is about to change.
 */
it('names the page-level verbs without naming a block', function (string $tool, string $expected): void {
    // Neither addresses a block, so the block label is irrelevant — and both are
    // slow enough (a write plus a skeleton) to be worth a line.
    expect(activityLine($tool, ['title' => 'Services']))->toBe($expected);
})->with([
    'adding a page' => ['CreatePage', 'Adding a page…'],
    'copying a page' => ['DuplicatePage', 'Copying this page…'],
]);

it('names which piece of site chrome it is editing', function (mixed $slot, string $expected): void {
    expect(activityLine('UpdateChrome', ['slot' => $slot]))->toBe($expected);
})->with([
    'the header' => ['header', 'Editing the site navigation…'],
    'the footer' => ['footer', 'Editing the site footer…'],
    // The arguments are the model's, so a missing or nonsense slot is ordinary.
    // It degrades to the navigation line rather than to silence: chrome IS being
    // edited, and the header is the far likelier of the two.
    'a missing slot' => [null, 'Editing the site navigation…'],
    'a nonsense slot' => ['sidebar', 'Editing the site navigation…'],
]);
