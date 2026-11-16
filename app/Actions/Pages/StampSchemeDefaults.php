<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Site\Blocks\BlockShape;
use App\Site\Blocks\BlockType;
use App\Site\Blocks\BlockVocabulary;
use App\Site\Blocks\LayoutAxis;
use App\Site\Blocks\SectionLayout;
use App\Site\Blocks\SectionSpacing;
use App\Site\Blocks\SectionTone;
use App\Themes\Scheme;

/**
 * Re-lays every block to the layout variants AND section appearances a theme
 * scheme was designed with.
 *
 * Named for the scheme rather than for variants (it was `StampVariantDefaults`)
 * because a scheme has two per-block halves, and applying only one of them
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
 * budget. Expanding a scheme server-side is also deterministic and testable in
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
 * TWO SEMANTICS LIVE HERE, and they must not be "unified":
 *
 *  - {@see handle()}/{@see stamp()} OVERWRITE. Their consumers are
 *    {@see \App\Ai\Tools\SetSiteStyle}'s `align_layouts` (a whole-site restyle
 *    is supposed to land both halves of the scheme over whatever was there)
 *    and {@see CreatePage} (fresh sample blocks, where fill
 *    and stamp coincide anyway).
 *  - {@see fill()} BACK-FILLS. Its one consumer is
 *    {@see \App\Actions\GenerateSiteDraft}: the draft agent now makes its own
 *    validated layout choices, and the scheme supplies defaults only for what
 *    the model omitted — overwriting here would re-create the six-identical-
 *    sites problem this split exists to kill.
 */
final readonly class StampSchemeDefaults
{
    public function __construct(private BlockVocabulary $vocabulary)
    {
        //
    }

    /**
     * Restamp a whole list — the OVERWRITE semantics (see the class docblock).
     * The chat path iterates {@see stamp()} itself, for the reason given there.
     *
     * @param  list<array{type: string, data: array<string, mixed>}>  $blocks
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    public function handle(array $blocks, Scheme $scheme): array
    {
        return array_map(function (array $block) use ($scheme): array {
            $block['data'] = $this->stamp($block['data'], $block['type'], $scheme);

            return $block;
        }, $blocks);
    }

    /**
     * Fill the scheme's defaults into freshly VALIDATED draft blocks — the
     * generation-path counterpart of {@see handle()}, with the opposite
     * precedence: a layout choice the model made (and
     * {@see \App\Ai\SiteDraftValidator} let through) survives, and the scheme
     * speaks only where the model was silent.
     *
     * The fallback is position-aware where the per-type maps cannot be:
     * walking the list in order, a fallback tone that would repeat the
     * previous section's base/muted band flips to the other, and a fallback
     * dark band directly after a dark band demotes to muted — so even a model
     * that chose nothing gets an alternating rhythm instead of a flat stack.
     * `hero` and `heading` are exempt for the same reasons the scheme maps
     * exclude them (the hero's variant decides its weight; a heading is a
     * divider, not a band), and neither advances the alternation state, so
     * the section after a hero alternates against the first real band.
     *
     * @param  list<array{type: string, data: array<string, mixed>}>  $blocks
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    public function fill(array $blocks, Scheme $scheme): array
    {
        $previousTone = null;

        return array_map(function (array $block) use ($scheme, &$previousTone): array {
            $block['data'] = $this->fillBlock($block['data'], $block['type'], $scheme, $previousTone);

            return $block;
        }, $blocks);
    }

    /**
     * Apply both halves of a scheme to one block's `data`.
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
    public function stamp(array $data, string $type, Scheme $scheme): array
    {
        $variant = $this->variantFor($type, $scheme);

        if ($variant !== null) {
            $data[BlockShape::VARIANT_KEY] = $variant;
        }

        $appearance = $this->appearanceFor($type, $scheme);

        // Absent means "this scheme has no opinion about this section", which is
        // NOT the same as "reset it": the operator may have set a background by
        // hand, and a scheme that lists nothing for the type should not be the
        // thing that silently discards it. Only an explicit entry writes.
        if ($appearance !== null) {
            $data[BlockShape::APPEARANCE_KEY] = $appearance;
        }

        return $data;
    }

    /**
     * The appearance one block type should carry under this scheme, or null when
     * the scheme says nothing about it — in which case the block keeps whatever
     * it has, and an untouched block keeps its view's own default.
     *
     * Values are validated against the enums rather than trusted: a scheme is
     * hand-authored PHP, so a typo here would otherwise reach a `class`
     * attribute. Axis APPLICABILITY is enforced at authoring time instead —
     * ThemeTest asserts every scheme entry names only axes its type
     * declares — so an inapplicable entry cannot ship. An entry that survives
     * validation empty answers null, so it cannot blank an operator's choice
     * either.
     *
     * @return array<string, string>|null
     */
    public function appearanceFor(string $type, Scheme $scheme): ?array
    {
        $declared = $scheme->blockAppearanceDefaults[$type] ?? null;

        if ($declared === null) {
            return null;
        }

        $axes = [];

        foreach (LayoutAxis::cases() as $axis) {
            $value = $declared[$axis->value] ?? null;

            if (is_string($value) && $axis->resolve($value) !== null) {
                $axes[$axis->value] = $value;
            }
        }

        return SectionLayout::store($axes);
    }

    /**
     * The variant one block type should carry under this scheme, or null when
     * the type has no variants at all (a single fixed view, or a type this
     * install does not know — a restyle must not be the thing that throws on an
     * unrecognised block, which is why the vocabulary miss is tolerated here
     * exactly as the render loop tolerates it).
     */
    public function variantFor(string $type, Scheme $scheme): ?string
    {
        $contract = $this->vocabulary->get($type);
        $variants = $contract instanceof BlockType ? $contract->variants : [];

        if ($variants === []) {
            return null;
        }

        $preferred = $scheme->blockVariantDefaults[$type] ?? null;

        // Falls back to the type's first variant when the scheme has no (valid)
        // default for it — e.g. a block type added after the scheme was authored.
        return in_array($preferred, $variants, true) ? $preferred : $variants[0];
    }

    /**
     * @param  array<array-key, mixed>  $stored
     */
    private function storedTone(array $stored): ?SectionTone
    {
        $value = $stored[BlockShape::TONE_KEY] ?? null;

        return is_string($value) ? SectionTone::tryFrom($value) : null;
    }

    /**
     * @param  array<array-key, mixed>  $stored
     */
    private function storedSpacing(array $stored): ?SectionSpacing
    {
        $value = $stored[BlockShape::SPACING_KEY] ?? null;

        return is_string($value) ? SectionSpacing::tryFrom($value) : null;
    }

    /**
     * One block's fill. `$previousTone` is the alternation state: the tone the
     * previous band-capable section resolved to, or null before the first one.
     * A section that writes nothing seeds the state from its CONTRACT default
     * ({@see BlockType::axisDefault()}) — the queryable form of the tone its
     * view used to hard-code, so testimonials' muted default now alternates
     * correctly instead of being guessed at as `base`.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fillBlock(array $data, string $type, Scheme $scheme, ?string &$previousTone): array
    {
        $contract = $this->vocabulary->get($type);

        if (! $contract instanceof BlockType) {
            // Unknown type: untouched, exactly as stamp() tolerates it.
            return $data;
        }

        // A valid model variant survives; missing or invalid gets the scheme's.
        if ($contract->variants !== [] && ! in_array($data[BlockShape::VARIANT_KEY] ?? null, $contract->variants, true)) {
            $data[BlockShape::VARIANT_KEY] = $this->variantFor($type, $scheme);
        }

        if ($type === 'hero' || $type === 'heading') {
            return $data;
        }

        $variant = is_string($data[BlockShape::VARIANT_KEY] ?? null) ? $data[BlockShape::VARIANT_KEY] : null;

        $stored = is_array($data[BlockShape::APPEARANCE_KEY] ?? null) ? $data[BlockShape::APPEARANCE_KEY] : [];
        $modelTone = $this->storedTone($stored);
        $modelSpacing = $this->storedSpacing($stored);

        $declared = $this->appearanceFor($type, $scheme) ?? [];
        $schemeTone = isset($declared[BlockShape::TONE_KEY]) ? SectionTone::from($declared[BlockShape::TONE_KEY]) : null;
        $schemeSpacing = isset($declared[BlockShape::SPACING_KEY]) ? SectionSpacing::from($declared[BlockShape::SPACING_KEY]) : null;

        $tone = $modelTone;
        $fallbackTone = null;

        if (! $tone instanceof SectionTone) {
            // The contract default is what the view will actually render when
            // nothing is written — the alternation must compare against THAT.
            $candidate = $schemeTone
                ?? SectionTone::from($contract->axisDefault(LayoutAxis::Tone, $variant));
            $flipped = false;

            if ($candidate === SectionTone::Inverted && $previousTone === SectionTone::Inverted->value) {
                $candidate = SectionTone::Muted;
                $flipped = true;
            } elseif (in_array($candidate, [SectionTone::Base, SectionTone::Muted], true) && $candidate->value === $previousTone) {
                $candidate = $candidate === SectionTone::Base ? SectionTone::Muted : SectionTone::Base;
                $flipped = true;
            }

            // Write only a real opinion: the scheme named this type, or the
            // alternation had to flip. A computed default over silence stays
            // unwritten — an untouched block keeps its view's own default.
            if ($flipped || $schemeTone !== null) {
                $fallbackTone = $candidate;
            }

            $tone = $candidate;
        }

        // The parametric axes fill the same way: a valid model choice
        // survives, the scheme speaks where the model was silent.
        $axes = [];

        foreach (LayoutAxis::extended() as $axis) {
            $model = $stored[$axis->value] ?? null;
            $axes[$axis->value] = is_string($model) && $axis->resolve($model) !== null
                ? $model
                : ($declared[$axis->value] ?? null);
        }

        $appearance = SectionLayout::store([
            BlockShape::TONE_KEY => ($modelTone ?? $fallbackTone)?->value,
            BlockShape::SPACING_KEY => ($modelSpacing ?? $schemeSpacing)?->value,
            ...$axes,
        ]);

        if ($appearance !== null) {
            $data[BlockShape::APPEARANCE_KEY] = $appearance;
        }

        $previousTone = $tone->value;

        return $data;
    }
}
