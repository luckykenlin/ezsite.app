<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use Illuminate\Support\Str;

/**
 * Duplicates one block in an editor blocks list, inserting the copy (with a
 * fresh transient key) directly after its source — the fastest way to build
 * repetitive layouts. Pure and loud on an unknown key — see
 * {@see AddPageBlock} for the state shape and the phase-2 AI rationale.
 */
final readonly class DuplicatePageBlock
{
    public function __construct(private FindPageBlockIndex $index)
    {
        //
    }

    /**
     * @param  list<array{key: string, type: string, data: array<string, mixed>}>  $blocks
     * @return array{blocks: list<array{key: string, type: string, data: array<string, mixed>}>, key: string}
     */
    public function handle(array $blocks, string $key): array
    {
        $index = $this->index->handle($blocks, $key);

        $copy = [
            'key' => (string) Str::uuid(),
            'type' => $blocks[$index]['type'],
            'data' => $blocks[$index]['data'],
        ];

        array_splice($blocks, $index + 1, 0, [$copy]);

        return ['blocks' => $blocks, 'key' => $copy['key']];
    }
}
