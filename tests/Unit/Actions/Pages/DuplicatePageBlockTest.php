<?php

declare(strict_types=1);

use App\Actions\Pages\DuplicatePageBlock;

it('inserts a fresh-keyed copy directly after the source block', function (): void {
    $blocks = [
        ['key' => 'a', 'type' => 'hero', 'data' => ['heading' => 'Welcome', 'features' => [['title' => 'Fast']]]],
        ['key' => 'b', 'type' => 'heading', 'data' => ['content' => 'About']],
    ];

    $result = resolve(DuplicatePageBlock::class)->handle($blocks, 'a');

    expect(array_column($result['blocks'], 'key'))->toBe(['a', $result['key'], 'b'])
        ->and($result['key'])->not->toBe('a')
        ->and($result['blocks'][1]['type'])->toBe('hero')
        ->and($result['blocks'][1]['data'])->toBe($blocks[0]['data']);
});
