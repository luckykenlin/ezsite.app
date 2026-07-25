<?php

declare(strict_types=1);

use App\Actions\Pages\MovePageBlock;

it('moves a block by a signed offset, clamped at both ends', function (string $key, int $offset, array $expectedOrder): void {
    $blocks = [
        ['key' => 'a', 'type' => 'hero', 'data' => []],
        ['key' => 'b', 'type' => 'heading', 'data' => []],
        ['key' => 'c', 'type' => 'cta', 'data' => []],
    ];

    $result = resolve(MovePageBlock::class)->handle($blocks, $key, $offset);

    expect(array_column($result, 'key'))->toBe($expectedOrder);
})->with([
    'down one' => ['a', 1, ['b', 'a', 'c']],
    'up one' => ['c', -1, ['a', 'c', 'b']],
    'first up is a no-op' => ['a', -1, ['a', 'b', 'c']],
    'last down is a no-op' => ['c', 1, ['a', 'b', 'c']],
    'large offset clamps to the end' => ['a', 9, ['b', 'c', 'a']],
]);

it('throws loudly on an unknown block key', function (): void {
    expect(fn (): array => resolve(MovePageBlock::class)->handle([], 'ghost', 1))
        ->toThrow(InvalidArgumentException::class, 'Unknown block key [ghost].');
});
