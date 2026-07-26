<?php

declare(strict_types=1);

use App\Actions\Pages\ReorderPageBlocks;

it('reorders the list to match the given key sequence', function (): void {
    $blocks = [
        ['key' => 'a', 'type' => 'hero', 'data' => []],
        ['key' => 'b', 'type' => 'heading', 'data' => []],
        ['key' => 'c', 'type' => 'cta', 'data' => []],
    ];

    $result = resolve(ReorderPageBlocks::class)->handle($blocks, ['c', 'a', 'b']);

    expect(array_column($result, 'key'))->toBe(['c', 'a', 'b'])
        ->and($result[1]['type'])->toBe('hero');
});

it('throws loudly when the sequence cardinality does not match', function (array $orderedKeys): void {
    $blocks = [
        ['key' => 'a', 'type' => 'hero', 'data' => []],
        ['key' => 'b', 'type' => 'heading', 'data' => []],
    ];

    expect(fn (): array => resolve(ReorderPageBlocks::class)->handle($blocks, $orderedKeys))
        ->toThrow(InvalidArgumentException::class, 'The given key sequence does not match the blocks list.');
})->with([
    'missing a key' => [['a']],
    'duplicated key' => [['a', 'a']],
    'extra key' => [['a', 'b', 'c']],
]);

it('throws loudly on a key the list does not contain', function (): void {
    $blocks = [
        ['key' => 'a', 'type' => 'hero', 'data' => []],
        ['key' => 'b', 'type' => 'heading', 'data' => []],
    ];

    expect(fn (): array => resolve(ReorderPageBlocks::class)->handle($blocks, ['a', 'ghost']))
        ->toThrow(InvalidArgumentException::class, 'Unknown block key [ghost].');
});
