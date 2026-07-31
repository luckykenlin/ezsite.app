<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Design\StylePreset;
use App\Site\Blocks\BlockShape;
use App\Site\Blocks\BlockType;
use App\Site\Blocks\BlockVocabulary;

/**
 * Re-lays every block to the layout variants a style preset was designed with.
 *
 * Extracted from {@see \App\Actions\GenerateSiteDraft}, which had this as a
 * private method and now calls it — so this removes a duplicate rather than
 * adding an abstraction. Its second caller is
 * {@see \App\Ai\Tools\SetSiteStyle}'s `align_layouts`, and that is what keeps a
 * whole-site restyle to ONE tool call: without it an eight-block page needs
 * nine, against `PageEditorAgent`'s `#[MaxSteps(12)]` and the 90-second turn
 * budget. Expanding a preset server-side is also deterministic and testable in
 * a way that N model choices are not.
 *
 * Two differences from the version it replaces, both because this now runs over
 * an OPERATOR'S draft rather than freshly validated model output:
 *
 *  - An unknown block type is left alone instead of fatally dereferencing a
 *    missing vocabulary entry. Unknown types genuinely occur on a real page —
 *    it is why the render loop is defensive — and a restyle must not be the
 *    thing that throws on one.
 *  - Blocks may carry the editor's transient `key`, which is preserved.
 *
 * Assigns into `data`'s existing `variant` slot rather than rebuilding the
 * array: `AddPageBlock` writes the variant FIRST, `pages.blocks` is `json` (not
 * `jsonb`) precisely to preserve key order, and `RecordPageRevision` compares
 * with `===` — so reordering keys would manufacture a spurious revision on
 * every Save.
 */
final readonly class StampVariantDefaults
{
    public function __construct(private BlockVocabulary $vocabulary)
    {
        //
    }

    /**
     * Restamp a whole list. Used by {@see \App\Actions\GenerateSiteDraft}, whose
     * blocks are freshly validated `{type, data}` with no editor key yet.
     *
     * The chat path calls {@see variantFor()} in its own loop instead. That is
     * not duplication for its own sake: PHPStan's array shapes are sealed, so
     * `{key, type, data}` is not a subtype of `{type, data}` and no single
     * signature accepts both without erasing the key that
     * {@see \App\Ai\PageDraft} requires back. The RULE — which variant, and what
     * to fall back to — has one implementation either way, which is the part
     * that was worth sharing.
     *
     * @param  list<array{type: string, data: array<string, mixed>}>  $blocks
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    public function handle(array $blocks, StylePreset $preset): array
    {
        return array_map(function (array $block) use ($preset): array {
            $variant = $this->variantFor($block['type'], $preset);

            if ($variant !== null) {
                $block['data'][BlockShape::VARIANT_KEY] = $variant;
            }

            return $block;
        }, $blocks);
    }

    /**
     * The variant one block type should carry under this preset, or null when
     * the type has no variants at all (a single fixed view, or a type this
     * install does not know — a restyle must not be the thing that throws on an
     * unrecognised block, which is why the vocabulary miss is tolerated here
     * exactly as the render loop tolerates it).
     */
    public function variantFor(string $type, StylePreset $preset): ?string
    {
        $contract = $this->vocabulary->get($type);
        $variants = $contract instanceof BlockType ? $contract->variants : [];

        if ($variants === []) {
            return null;
        }

        $preferred = $preset->blockVariantDefaults()[$type] ?? null;

        // Falls back to the type's first variant when the preset has no (valid)
        // default for it — e.g. a block type added after the preset was authored.
        return in_array($preferred, $variants, true) ? $preferred : $variants[0];
    }
}
