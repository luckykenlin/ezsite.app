<?php

declare(strict_types=1);

use App\Actions\Pages\DuplicatePageBlock;
use App\Actions\Pages\FindPageBlockIndex;
use App\Actions\Pages\MovePageBlock;
use App\Actions\Pages\RemovePageBlock;
use App\Actions\Pages\UpdatePageBlock;

it('locates a block by its transient key', function (): void {
    $blocks = [
        ['key' => 'a', 'type' => 'hero', 'data' => []],
        ['key' => 'b', 'type' => 'heading', 'data' => []],
    ];

    expect(resolve(FindPageBlockIndex::class)->handle($blocks, 'b'))->toBe(1);
});

/*
 * Every key-addressed page-block action delegates its lookup here, so a stale
 * key (block already removed, AI hallucination) fails loud instead of silently
 * mutating the wrong block. Each action is invoked for real, so replacing a
 * delegation with a silent array_search still fails.
 */
it('is loud on an unknown block key, whichever action addresses it', function (Closure $call): void {
    expect($call)->toThrow(InvalidArgumentException::class, 'Unknown block key [ghost].');
})->with([
    'remove' => [fn (): array => resolve(RemovePageBlock::class)->handle([], 'ghost')],
    'move' => [fn (): array => resolve(MovePageBlock::class)->handle([], 'ghost', 1)],
    'duplicate' => [fn (): array => resolve(DuplicatePageBlock::class)->handle([], 'ghost')],
    'update' => [fn (): array => resolve(UpdatePageBlock::class)->handle([], 'ghost', [])],
]);
