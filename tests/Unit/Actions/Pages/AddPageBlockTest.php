<?php

declare(strict_types=1);

use App\Actions\Pages\AddPageBlock;
use App\Filament\Fabricator\PageBlocks\Hero;

it('appends a block with its default variant pre-filled', function (): void {
    $result = resolve(AddPageBlock::class)->handle([], 'hero');

    expect($result['blocks'])->toHaveCount(1)
        ->and($result['blocks'][0]['key'])->toBe($result['key'])
        ->and($result['blocks'][0]['type'])->toBe('hero')
        ->and($result['blocks'][0]['data'])->toBe(['variant' => Hero::defaultVariant()]);
});

it('appends a variant-less block with empty data', function (): void {
    $result = resolve(AddPageBlock::class)->handle([], 'heading');

    expect($result['blocks'][0]['data'])->toBeEmpty();
});

it('inserts at an explicit position and clamps out-of-range positions', function (int $position, int $expectedIndex): void {
    $blocks = [
        ['key' => 'a', 'type' => 'heading', 'data' => []],
        ['key' => 'b', 'type' => 'heading', 'data' => []],
    ];

    $result = resolve(AddPageBlock::class)->handle($blocks, 'cta', $position);

    expect(array_column($result['blocks'], 'type'))->toHaveCount(3)
        ->and($result['blocks'][$expectedIndex]['type'])->toBe('cta')
        ->and($result['blocks'][$expectedIndex]['key'])->toBe($result['key']);
})->with([
    'at the start' => [0, 0],
    'in the middle' => [1, 1],
    'past the end clamps to the end' => [9, 2],
    'negative clamps to the start' => [-3, 0],
]);

it('generates a unique key per added block', function (): void {
    $first = resolve(AddPageBlock::class)->handle([], 'hero');
    $second = resolve(AddPageBlock::class)->handle($first['blocks'], 'hero');

    expect($second['key'])->not->toBe($first['key'])
        ->and(array_column($second['blocks'], 'key'))->toHaveCount(2);
});

it('throws loudly on an unknown block type', function (): void {
    expect(fn (): array => resolve(AddPageBlock::class)->handle([], 'carousel'))
        ->toThrow(InvalidArgumentException::class, 'Unknown block type [carousel].');
});
