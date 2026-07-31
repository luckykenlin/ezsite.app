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
    ]],
    'a non-string reply' => [['reply' => 42, 'blocks' => 'oops', 'activity' => 'not a list'], [
        'status' => 'running',
        'reply' => '',
        'blocks' => null,
        'failed' => false,
        'activity' => [],
    ]],
]);
