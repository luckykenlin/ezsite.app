<?php

declare(strict_types=1);

use App\Actions\Pages\CacheChatTurn;
use Illuminate\Support\Facades\Cache;

/*
 * The handoff between a chat turn on the queue worker and the editor polling for
 * it. Reads are defensive on purpose: the payload comes back from an external
 * store and its blocks go into the editor's state and from there into the page,
 * so anything malformed is dropped here rather than downstream.
 */

function chatTurns(): CacheChatTurn
{
    return resolve(CacheChatTurn::class);
}

it('reports a turn that is still streaming as running, with no result to apply', function (): void {
    chatTurns()->handle('tok', 'Shortening the');

    expect(chatTurns()->read('tok'))->toBe([
        'status' => 'running',
        'reply' => 'Shortening the',
        'blocks' => null,
        'failed' => false,
        'activity' => [],
        'design' => null,
        'chrome' => null,
        'preview' => 0,
    ]);
});

it('reports a finished turn with its blocks', function (): void {
    $blocks = [['key' => 'k1', 'type' => 'hero', 'data' => ['heading' => 'Fresh bread daily']]];

    chatTurns()->handle('tok', 'Shortened it.', $blocks);

    expect(chatTurns()->read('tok'))->toBe([
        'status' => 'done',
        'reply' => 'Shortened it.',
        'blocks' => $blocks,
        'failed' => false,
        'activity' => [],
        'design' => null,
        'chrome' => null,
        'preview' => 0,
    ]);
});

it('carries the activity lines the turn has produced so far', function (): void {
    // The progress list the chat rail draws above the reply. Cumulative like the
    // reply itself, so a browser that reconnects mid-turn is not missing the
    // steps it was disconnected for.
    chatTurns()->handle('tok', '', activity: ['Rewriting the Hero block…', 'Adding a Cta block…']);

    expect(chatTurns()->read('tok')['activity'])
        ->toBe(['Rewriting the Hero block…', 'Adding a Cta block…']);
});

it('drops activity entries that are not lines of text', function (): void {
    // They are rendered in the panel, so a malformed entry is dropped here rather
    // than reaching a view — the same defensiveness the blocks get.
    Cache::put(CacheChatTurn::key('tok'), [
        'status' => 'running',
        'reply' => '',
        'activity' => ['Rewriting the Hero block…', ['nested'], 42],
    ]);

    expect(chatTurns()->read('tok')['activity'])->toBe(['Rewriting the Hero block…']);
});

it('carries the paint counter, reading junk as never-painted', function (): void {
    // The counter tells the SSE tail the canvas preview moved; anything but a
    // non-negative int reads as zero, which only costs a repaint not needed.
    chatTurns()->handle('tok', '', preview: 3);

    expect(chatTurns()->read('tok')['preview'])->toBe(3);

    Cache::put(CacheChatTurn::key('junk'), ['preview' => -2]);
    Cache::put(CacheChatTurn::key('junk2'), ['preview' => 'three']);

    expect(chatTurns()->read('junk')['preview'])->toBe(0)
        ->and(chatTurns()->read('junk2')['preview'])->toBe(0);
});

it('carries the unchanged blocks of a failed turn', function (): void {
    $blocks = [['key' => 'k1', 'type' => 'hero', 'data' => []]];

    chatTurns()->handle('tok', "Sorry — I couldn't reach the assistant.", $blocks, failed: true);

    expect(chatTurns()->read('tok')['failed'])->toBeTrue()
        ->and(chatTurns()->read('tok')['status'])->toBe('done');
});

it('has nothing to report for an unknown or forgotten token', function (): void {
    chatTurns()->handle('tok', 'Done.', []);
    chatTurns()->forget('tok');

    expect(chatTurns()->read('tok'))->toBeNull()
        ->and(chatTurns()->read('never-existed'))->toBeNull();
});

it('drops a stored entry that does not carry the editor block shape', function (): void {
    // Written past the action, the way a stale payload from an older release or
    // a truncated write would look.
    Cache::put(CacheChatTurn::key('tok'), [
        'status' => 'done',
        'reply' => 'Done.',
        'failed' => false,
        'blocks' => [
            ['key' => 'k1', 'type' => 'hero', 'data' => ['heading' => 'Keep me']],
            'not a block',
            ['type' => 'hero', 'data' => []],   // no key to address it by
            ['key' => 'k2', 'data' => []],      // no type to render
            ['key' => 'k3', 'type' => 'cta'],   // no data at all is still usable
        ],
    ]);

    expect(chatTurns()->read('tok')['blocks'])->toBe([
        ['key' => 'k1', 'type' => 'hero', 'data' => ['heading' => 'Keep me']],
        ['key' => 'k3', 'type' => 'cta', 'data' => []],
    ]);
});

it('reads a structurally broken payload as nothing rather than throwing', function (mixed $payload, mixed $expected): void {
    Cache::put(CacheChatTurn::key('tok'), $payload);

    expect(chatTurns()->read('tok'))->toBe($expected);
})->with([
    'not an array at all' => ['just a string', null],
    'an array of nothing' => [[], [
        'status' => 'running',
        'reply' => '',
        'blocks' => null,
        'failed' => false,
        'activity' => [],
        'design' => null,
        'chrome' => null,
        'preview' => 0,
    ]],
    'a non-string reply' => [['reply' => 42, 'blocks' => 'oops', 'activity' => 'not a list'], [
        'status' => 'running',
        'reply' => '',
        'blocks' => null,
        'failed' => false,
        'activity' => [],
        'design' => null,
        'chrome' => null,
        'preview' => 0,
    ]],
]);

/*
 * The staged style crosses the same untrusted boundary the blocks do, and lands
 * in $designDraft — from which ThemeVariables compiles a <style> tag. Every value
 * is re-resolved against a token enum downstream, so junk cannot reach CSS; an
 * unexpected KEY would still ride into the editor's state, so only known ones
 * survive the read.
 */
it('round-trips a staged style and keeps only the keys the editor knows', function (): void {
    chatTurns()->handle('tok', 'Done.', [], design: [
        'preset' => 'warm-craft',
        'palette' => 'warm-sand',
        'font_pair' => 42,
        'smuggled' => 'value',
    ]);

    expect(chatTurns()->read('tok')['design'])->toBe([
        'preset' => 'warm-craft',
        'palette' => 'warm-sand',
        // Non-strings become null rather than being passed along.
        'font_pair' => null,
        'type_style' => null,
        'radius' => null,
        'density' => null,
        'divider' => null,
        'accent' => null,
        'motion' => null,
    ]);
});

/*
 * The chrome payload is normalised for the same reason the blocks are: it comes
 * back from an external store and goes straight into the editor's `$chrome`
 * draft, and from there — via SaveSiteChrome — into `site_settings`.
 */
it('keeps only the two real chrome slots, with string-keyed data', function (): void {
    $turns = resolve(CacheChatTurn::class);

    $turns->handle('t-chrome', 'Done.', [], chrome: [
        'header' => ['type' => 'header', 'data' => ['nav_links' => [['label' => 'Home', 'url' => '/']], 0 => 'numeric']],
        // A slot that does not exist cannot be invented by a malformed payload.
        'sidebar' => ['type' => 'sidebar', 'data' => ['note' => 'nope']],
        // A non-array entry is dropped rather than reaching the editor.
        'footer' => 'not an entry',
    ]);

    $chrome = $turns->read('t-chrome')['chrome'];

    expect(array_keys((array) $chrome))->toBe(['header'])
        ->and($chrome['header']['type'])->toBe('header')
        // The cast to string does not survive as a string KEY — PHP normalises a
        // numeric-looking key straight back to an int. What it buys is that the
        // array stops being a list, so json_encode emits an object rather than an
        // array and the stored shape does not silently change (see BlockData).
        ->and(array_keys($chrome['header']['data']))->toBe(['nav_links', 0])
        ->and(array_is_list($chrome['header']['data']))->toBeFalse();
});

it('reads absent or unusable chrome as "the turn left it alone"', function (mixed $chrome): void {
    $turns = resolve(CacheChatTurn::class);

    $turns->handle('t-none', 'Done.', [], chrome: $chrome);

    expect($turns->read('t-none')['chrome'])->toBeNull();
})->with([
    'nothing staged' => [null],
    'no recognised slot' => [[['type' => 'header']]],
]);
