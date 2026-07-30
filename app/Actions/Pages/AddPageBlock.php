<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Site\Blocks\BlockShape;
use App\Site\Blocks\BlockType;
use App\Site\Blocks\BlockVocabulary;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Appends (or inserts) a new block into an editor blocks list.
 *
 * The blocks list is the page editor's working state: the persisted
 * `[{type, data}]` shape plus a transient `key` (uuid) per entry that gives
 * each block a stable identity for canvas selection. This action is pure
 * (arrays in, arrays out) so the phase-2 AI tools can call it against the
 * same state the human editor uses. Unknown types fail LOUD, mirroring
 * {@see \App\Actions\UpdateDesignTokens}: a misbehaving caller gets an
 * exception, never a silent no-op.
 */
final readonly class AddPageBlock
{
    public function __construct(private BlockVocabulary $vocabulary)
    {
        //
    }

    /**
     * @param  list<array{key: string, type: string, data: array<string, mixed>}>  $blocks
     * @return array{blocks: list<array{key: string, type: string, data: array<string, mixed>}>, key: string}
     */
    public function handle(array $blocks, string $type, ?int $position = null): array
    {
        $blockType = $this->vocabulary->get($type);

        // Chrome is rejected here, not only in the UI that lists types: every
        // `wire:click`-able method is callable from the browser with any
        // argument, so the library filter alone is a suggestion, not a boundary.
        // Chrome never legitimately flows through this action — the editor builds
        // its slot entries in defaultChromeEntry() and SiteChromeSettings uses a
        // Filament Builder.
        throw_unless(
            $blockType instanceof BlockType && ! $blockType->isChrome(),
            InvalidArgumentException::class,
            sprintf('Block type [%s] cannot be added to a page.', $type),
        );

        $key = (string) Str::uuid();

        // A fresh block lands with its sample content: it renders presentable
        // immediately and passes its own validation — an empty required field
        // would otherwise block every next action.
        $data = $blockType->sample;

        if ($blockType->variants !== []) {
            $data = [BlockShape::VARIANT_KEY => $blockType->defaultVariant()] + $data;
        }

        $block = [
            'key' => $key,
            'type' => $type,
            'data' => $data,
        ];

        $position = min(max($position ?? count($blocks), 0), count($blocks));

        return [
            'blocks' => [
                ...array_slice($blocks, 0, $position),
                $block,
                ...array_slice($blocks, $position),
            ],
            'key' => $key,
        ];
    }
}
