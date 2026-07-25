<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use InvalidArgumentException;

/**
 * Locates a block's position in an editor blocks list by its transient key.
 * The shared addressing primitive for the other page-block actions; loud on
 * an unknown key so a stale key (block already removed, AI hallucination)
 * surfaces immediately instead of silently mutating the wrong block.
 */
final readonly class FindPageBlockIndex
{
    /**
     * @param  list<array{key: string, type: string, data: array<string, mixed>}>  $blocks
     */
    public function handle(array $blocks, string $key): int
    {
        $index = array_search($key, array_column($blocks, 'key'), true);

        throw_if($index === false, InvalidArgumentException::class, sprintf('Unknown block key [%s].', $key));

        return (int) $index;
    }
}
