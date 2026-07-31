<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Site\Blocks\BlockData;
use Illuminate\Support\Str;

/**
 * Turn a stored `[{type, data}]` list into the editor's working shape by giving
 * every entry a transient uuid `key`.
 *
 * Keys are what the canvas selects by and what every `App\Actions\Pages\*` verb
 * addresses; they are minted per mount and stripped again on save, so they never
 * reach the database.
 *
 * Extracted from the page editor because a stored block list now arrives from two
 * places: `pages.blocks` on mount, and a {@see \App\Models\PageRevision} the
 * operator chose to restore.
 *
 * Structurally broken entries are normalised rather than dropped — a block with
 * no type becomes an empty-typed one, which renders as a placeholder on the canvas
 * and stays deletable. Silently removing it would make a page quietly lose content
 * that a human might have been able to identify.
 */
final readonly class KeyEditorBlocks
{
    /**
     * @param  array<array-key, mixed>  $stored
     * @return list<array{key: string, type: string, data: array<string, mixed>}>
     */
    public function handle(array $stored): array
    {
        return array_values(array_map(
            static function (mixed $block): array {
                $block = is_array($block) ? $block : [];
                $type = $block['type'] ?? null;
                $data = $block['data'] ?? null;

                return [
                    'key' => (string) Str::uuid(),
                    'type' => is_string($type) ? $type : '',
                    'data' => is_array($data) ? BlockData::stringKeyed($data) : [],
                ];
            },
            $stored,
        ));
    }
}
