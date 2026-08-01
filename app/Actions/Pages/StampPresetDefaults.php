<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Design\StylePreset;
use App\Site\Blocks\BlockShape;
use App\Site\Blocks\BlockType;
use App\Site\Blocks\BlockVocabulary;
use App\Site\Blocks\SectionAppearance;
use App\Site\Blocks\SectionSpacing;
use App\Site\Blocks\SectionTone;

/**
 * Re-lays every block to the layout variants AND section appearances a style
 * preset was designed with.
 *
 * Named for the preset rather than for variants (it was `StampVariantDefaults`)
 * because a preset now has two per-block halves, and applying only one of them
 * is what a whole-site restyle must never do: layouts aligned to
 * `bold-editorial` while the backgrounds still say `calm-coastal` is a page that
 * looks broken in a way neither setting explains.
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
 */
final readonly class StampPresetDefaults
{
    public function __construct(private BlockVocabulary $vocabulary)
    {
        //
    }

    /**
     * Restamp a whole list. Used by {@see \App\Actions\GenerateSiteDraft}, whose
     * blocks are freshly validated `{type, data}` with no editor key yet; the chat
     * path iterates {@see stamp()} itself, for the reason given there.
     *
     * @param  list<array{type: string, data: array<string, mixed>}>  $blocks
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    public function handle(array $blocks, StylePreset $preset): array
    {
        return array_map(function (array $block) use ($preset): array {
            $block['data'] = $this->stamp($block['data'], $block['type'], $preset);

            return $block;
        }, $blocks);
    }

    /**
     * Apply both halves of a preset to one block's `data`.
     *
     * Shared by this action's own loop and by {@see \App\Ai\Tools\SetSiteStyle},
     * which cannot call {@see handle()} because PHPStan's array shapes are sealed
     * and its blocks carry the editor's transient `key`. The RULE lives here once;
     * only the iteration differs.
     *
     * Assigns into the existing slots rather than rebuilding `data`, because key
     * order is load-bearing — see {@see \App\Ai\Tools\SetBlockVariant}.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function stamp(array $data, string $type, StylePreset $preset): array
    {
        $variant = $this->variantFor($type, $preset);

        if ($variant !== null) {
            $data[BlockShape::VARIANT_KEY] = $variant;
        }

        $appearance = $this->appearanceFor($type, $preset);

        // Absent means "this preset has no opinion about this section", which is
        // NOT the same as "reset it": the operator may have set a background by
        // hand, and a preset that lists nothing for the type should not be the
        // thing that silently discards it. Only an explicit entry writes.
        if ($appearance !== null) {
            $data[BlockShape::APPEARANCE_KEY] = $appearance;
        }

        return $data;
    }

    /**
     * The appearance one block type should carry under this preset, or null when
     * the preset says nothing about it — in which case the block keeps whatever
     * it has, and an untouched block keeps its view's own default.
     *
     * Values are validated against the enums rather than trusted: a preset is
     * hand-authored PHP, so a typo here would otherwise reach a `class`
     * attribute. An entry that survives validation empty (every key misspelled)
     * answers null, so it cannot blank an operator's choice either.
     *
     * @return array<string, string>|null
     */
    public function appearanceFor(string $type, StylePreset $preset): ?array
    {
        $declared = $preset->blockAppearanceDefaults()[$type] ?? null;

        if ($declared === null) {
            return null;
        }

        return SectionAppearance::store(
            SectionTone::tryFrom($declared[BlockShape::TONE_KEY] ?? ''),
            SectionSpacing::tryFrom($declared[BlockShape::SPACING_KEY] ?? ''),
        );
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
