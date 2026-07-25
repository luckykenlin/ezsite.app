<?php

declare(strict_types=1);

use App\Actions\Pages\RemovePageBlock;

it('removes the addressed block and reindexes the list', function (): void {
    $blocks = [
        ['key' => 'a', 'type' => 'hero', 'data' => []],
        ['key' => 'b', 'type' => 'heading', 'data' => []],
        ['key' => 'c', 'type' => 'cta', 'data' => []],
    ];

    $result = resolve(RemovePageBlock::class)->handle($blocks, 'b');

    expect(array_column($result, 'key'))->toBe(['a', 'c'])
        ->and(array_is_list($result))->toBeTrue();
});

it('throws loudly on an unknown block key', function (): void {
    expect(fn (): array => resolve(RemovePageBlock::class)->handle([], 'ghost'))
        ->toThrow(InvalidArgumentException::class, 'Unknown block key [ghost].');
});
