<?php

declare(strict_types=1);

namespace App\Actions\Pages;

/**
 * Removes one block from an editor blocks list, addressed by its transient
 * key. Pure and loud on an unknown key — see {@see AddPageBlock} for the
 * state shape and the phase-2 AI rationale.
 */
final readonly class RemovePageBlock
{
    public function __construct(private FindPageBlockIndex $index)
    {
        //
    }

    /**
     * @param  list<array{key: string, type: string, data: array<string, mixed>}>  $blocks
     * @return list<array{key: string, type: string, data: array<string, mixed>}>
     */
    public function handle(array $blocks, string $key): array
    {
        $index = $this->index->handle($blocks, $key);

        unset($blocks[$index]);

        return array_values($blocks);
    }
}
