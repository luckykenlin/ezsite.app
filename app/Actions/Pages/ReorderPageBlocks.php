<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use InvalidArgumentException;

/**
 * Reorders an editor blocks list to match an explicit key sequence — the
 * drag-and-drop counterpart to {@see MovePageBlock}'s single-step moves, and
 * the whole-page "rearrange" verb the phase-2 AI tools get for free. Loud on
 * any mismatch between the given sequence and the list's actual keys, so a
 * stale drag payload (block removed mid-drag, AI hallucination) surfaces
 * instead of silently dropping or duplicating blocks.
 */
final readonly class ReorderPageBlocks
{
    /**
     * @param  list<array{key: string, type: string, data: array<string, mixed>}>  $blocks
     * @param  list<string>  $orderedKeys
     * @return list<array{key: string, type: string, data: array<string, mixed>}>
     */
    public function handle(array $blocks, array $orderedKeys): array
    {
        throw_if(
            count(array_unique($orderedKeys)) !== count($blocks),
            InvalidArgumentException::class,
            'The given key sequence does not match the blocks list.',
        );

        $byKey = array_column($blocks, null, 'key');

        $ordered = [];

        foreach ($orderedKeys as $key) {
            $block = $byKey[$key] ?? null;

            throw_if($block === null, InvalidArgumentException::class, sprintf('Unknown block key [%s].', $key));

            $ordered[] = $block;
        }

        return $ordered;
    }
}
