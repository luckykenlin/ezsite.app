<?php

declare(strict_types=1);

use App\Actions\Pages\UpdatePageBlock;

it('replaces the addressed block data and leaves siblings untouched', function (): void {
    $blocks = [
        ['key' => 'a', 'type' => 'hero', 'data' => ['heading' => 'Old']],
        ['key' => 'b', 'type' => 'heading', 'data' => ['content' => 'Keep me']],
    ];

    $result = resolve(UpdatePageBlock::class)->handle($blocks, 'a', ['heading' => 'New', 'eyebrow' => 'Hi']);

    expect($result[0]['data'])->toBe(['heading' => 'New', 'eyebrow' => 'Hi'])
        ->and($result[1])->toBe($blocks[1]);
});

it('throws loudly on an unknown block key', function (): void {
    expect(fn (): array => resolve(UpdatePageBlock::class)->handle([], 'ghost', []))
        ->toThrow(InvalidArgumentException::class, 'Unknown block key [ghost].');
});
