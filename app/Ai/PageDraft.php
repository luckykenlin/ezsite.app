<?php

declare(strict_types=1);

namespace App\Ai;

use App\Site\Blocks\BlockShape;
use Illuminate\Support\Str;

/**
 * The mutable working copy of a page's blocks that the chat tools operate on.
 *
 * A chat turn can call several tools; each needs to see the previous one's
 * result, so the tools share ONE of these and mutate it in place. Whatever the
 * draft holds when the turn ends is handed to
 * {@see \App\Filament\Tenant\Resources\PageResource\Pages\PageEditor::applyBlocks()},
 * which snapshots it onto the undo stack — so an AI edit is undoable and still
 * needs an explicit Save, exactly like a hand edit.
 *
 * Holds the editor's block shape (persisted `{type, data}` plus the transient
 * uuid `key`); mutations go through the pure `App\Actions\Pages\*` actions so
 * the AI and the human editor share one implementation.
 */
final class PageDraft
{
    /**
     * @param  list<array{key: string, type: string, data: array<string, mixed>}>  $blocks
     */
    public function __construct(private array $blocks)
    {
        //
    }

    /**
     * @return list<array{key: string, type: string, data: array<string, mixed>}>
     */
    public function blocks(): array
    {
        return $this->blocks;
    }

    /**
     * @param  list<array{key: string, type: string, data: array<string, mixed>}>  $blocks
     */
    public function replace(array $blocks): void
    {
        $this->blocks = $blocks;
    }

    /**
     * The block at a key, or null — the tools' "does this key exist" check,
     * kept here so they never index into the list themselves.
     *
     * @return array{key: string, type: string, data: array<string, mixed>}|null
     */
    public function find(string $key): ?array
    {
        foreach ($this->blocks as $block) {
            if ($block['key'] === $key) {
                return $block;
            }
        }

        return null;
    }

    /**
     * The page as the model should see it: one line per block with its
     * position, addressable key, type, layout variant and content. This is
     * every tool's return value, so the model always re-reads the real state
     * after each edit instead of tracking it in its head.
     */
    public function outline(): string
    {
        if ($this->blocks === []) {
            return 'The page is empty — it has no blocks.';
        }

        $lines = [];

        foreach ($this->blocks as $position => $block) {
            $data = $block['data'];
            $variant = $data[BlockShape::VARIANT_KEY] ?? null;
            unset($data[BlockShape::VARIANT_KEY], $data[BlockShape::BIND_KEY]);

            $lines[] = sprintf(
                '%d. %s%s [key: %s]%s',
                $position,
                $block['type'] === '' ? 'unknown' : $block['type'],
                is_string($variant) ? ' ('.$variant.')' : '',
                $block['key'],
                $data === [] ? ' — no content' : "\n   ".$this->encode($data),
            );
        }

        return "Current page blocks:\n".implode("\n", $lines);
    }

    /**
     * A block's content, compacted for the outline: long prose is truncated
     * (the model needs to recognize a block, not re-read a whole page) while
     * short fields stay verbatim so it can edit them precisely.
     *
     * @param  array<string, mixed>  $data
     */
    private function encode(array $data): string
    {
        $compact = array_map(
            static fn (mixed $value): mixed => is_string($value) ? Str::limit($value, 120) : $value,
            $data,
        );

        return json_encode($compact, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
