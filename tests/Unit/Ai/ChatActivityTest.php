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
]);

it('says nothing at all about a tool it does not know', function (): void {
    // A generic "working…" would be a made-up line replacing an honest silence —
    // and the caller only streams what this returns.
    expect(activityLine('SomeFutureTool', ['key' => 'k1']))->toBeNull();
});
