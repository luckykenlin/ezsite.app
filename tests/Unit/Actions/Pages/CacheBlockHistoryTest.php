<?php

declare(strict_types=1);

use App\Actions\Pages\CacheBlockHistory;
use Illuminate\Support\Facades\Cache;

function historyEntry(string $key = 'k1', string $content = 'One'): array
{
    return [
        'blocks' => [['key' => $key, 'type' => 'heading', 'data' => ['content' => $content]]],
        'selectedBlockKey' => $key,
    ];
}

it('round-trips both stacks under a token', function (): void {
    $cache = resolve(CacheBlockHistory::class);

    $cache->handle(1, [historyEntry('a')], [historyEntry('b')]);

    $read = $cache->read(1);

    expect($read['history'])->toBe([historyEntry('a')])
        ->and($read['future'])->toBe([historyEntry('b')]);
});

it('reads as empty when the page has no history', function (): void {
    // A stale or evicted entry must read as "nothing to undo", never as an error:
    // undo depth is convenience state, so losing it cannot break the editor.
    expect(resolve(CacheBlockHistory::class)->read(999))
        ->toBe(['history' => [], 'future' => []]);
});

it('keeps only the most recent snapshots per stack', function (): void {
    $cache = resolve(CacheBlockHistory::class);

    $entries = array_map(fn (int $i): array => historyEntry('k'.$i), range(1, CacheBlockHistory::LIMIT + 10));

    $cache->handle(1, $entries, []);
    $history = $cache->read(1)['history'];

    // Oldest are dropped, newest survive, so one long session cannot grow the
    // cache entry without bound.
    expect($history)->toHaveCount(CacheBlockHistory::LIMIT)
        ->and($history[0])->toBe(historyEntry('k11'))
        ->and($history[CacheBlockHistory::LIMIT - 1])->toBe(historyEntry('k'.(CacheBlockHistory::LIMIT + 10)));
});

it('drops malformed snapshots instead of letting them reach the page', function (): void {
    // These entries flow into $blocks and from there into pages.blocks on the
    // next Save, so anything the store hands back that is not the editor's block
    // shape is discarded here rather than downstream.
    Cache::put(CacheBlockHistory::key(1), [
        'history' => [
            'not an array',
            ['selectedBlockKey' => 'x'],                    // no blocks at all
            ['blocks' => 'not an array'],
            [
                'blocks' => [
                    'not an array',
                    ['type' => 'heading', 'data' => []],     // no key
                    ['key' => 'k1', 'data' => []],           // no type
                    ['key' => 'k2', 'type' => 'heading'],    // no data -> defaults to []
                    ['key' => 'k3', 'type' => 'heading', 'data' => 'not an array'],
                    ['key' => 'k4', 'type' => 'heading', 'data' => [0 => 'numeric key']],
                ],
                'selectedBlockKey' => 99,                    // not a string -> null
            ],
        ],
        'future' => 'not an array',
    ], now()->addHour());

    $read = resolve(CacheBlockHistory::class)->read(1);

    expect($read['future'])->toBeEmpty()
        ->and($read['history'])->toBe([[
            'blocks' => [
                ['key' => 'k2', 'type' => 'heading', 'data' => []],
                ['key' => 'k3', 'type' => 'heading', 'data' => []],
                // Numeric keys are stringified, or json_encode would emit a JSON
                // array where the stored shape is an object.
                ['key' => 'k4', 'type' => 'heading', 'data' => ['0' => 'numeric key']],
            ],
            'selectedBlockKey' => null,
        ]]);
});

it('keeps a separate stack per page', function (): void {
    // Keyed by page, not by the editor's per-mount token — that is what lets undo
    // survive a reload. Two different pages must still not see each other's.
    $cache = resolve(CacheBlockHistory::class);

    $cache->handle(1, [historyEntry('page-one')], []);
    $cache->handle(2, [historyEntry('page-two')], []);

    expect($cache->read(1)['history'])->toBe([historyEntry('page-one')])
        ->and($cache->read(2)['history'])->toBe([historyEntry('page-two')]);
});
