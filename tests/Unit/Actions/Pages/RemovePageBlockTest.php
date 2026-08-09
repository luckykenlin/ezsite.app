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
        ->and($result)->toBeList();
});
