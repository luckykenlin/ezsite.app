<?php

declare(strict_types=1);

namespace App\Actions\Pages;

/**
 * Moves one block within an editor blocks list by a signed offset (the
 * structure list's up/down buttons pass ±1), clamped at both ends so moving
 * the first block up or the last block down is a harmless no-op. Pure and
 * loud on an unknown key — see {@see AddPageBlock} for the state shape and
 * the phase-2 AI rationale.
 */
final readonly class MovePageBlock
{
    public function __construct(private FindPageBlockIndex $index)
    {
        //
    }

    /**
     * @param  list<array{key: string, type: string, data: array<string, mixed>}>  $blocks
     * @return list<array{key: string, type: string, data: array<string, mixed>}>
     */
    public function handle(array $blocks, string $key, int $offset): array
    {
        $index = $this->index->handle($blocks, $key);

        $target = min(max($index + $offset, 0), count($blocks) - 1);

        [$block] = array_splice($blocks, $index, 1);
        array_splice($blocks, $target, 0, [$block]);

        return $blocks;
    }
}
