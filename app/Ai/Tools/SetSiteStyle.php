<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Actions\Pages\StampVariantDefaults;
use App\Ai\PageDraft;
use App\Ai\SiteStyleDraft;
use App\Design\StylePreset;
use App\Design\TokenKey;
use App\Design\TokenOptions;
use App\Design\TokenSelection;
use App\Site\Blocks\BlockShape;
use BackedEnum;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Sets the look of the WHOLE site — "make it feel more premium", "warmer",
 * "more like Apple".
 *
 * Nothing here writes. It stages a {@see SiteStyleDraft}, which rides back to
 * the editor as a canvas preview; {@see \App\Actions\SaveDesignSelection},
 * behind the operator's "Apply to site", stays the only write path. That is not
 * caution for its own sake: tokens are site-scoped and
 * {@see \App\Design\ThemeVariables::style()} reads the SAVED ones on every
 * public render, so a tool that persisted would restyle a live website while
 * its owner was still reading the reply.
 *
 * Presets first, tokens second, by design. A preset is a combination that was
 * designed together, and {@see StylePreset}'s docblock names them "the guard
 * rail against jarring free-form variant × token combinations" — so an
 * adjective resolves to a preset, and fine-tuning is for when the operator
 * named the thing itself ("rounder corners"). Fine-tuning detaches the preset
 * marker, exactly as it does through the Design modal, because
 * {@see \App\Design\DesignTokens::with()} says so.
 *
 * `align_layouts` is what keeps a whole-site restyle to ONE call. Without it an
 * eight-block page needs nine, against `#[MaxSteps(12)]` and a 90-second turn
 * budget enforced between stream events — the difference between a broad
 * restyle finishing and timing out.
 */
final readonly class SetSiteStyle implements Tool
{
    public function __construct(
        private SiteStyleDraft $style,
        private PageDraft $draft,
        private StampVariantDefaults $stampVariantDefaults,
    ) {
        //
    }

    public function description(): string
    {
        return 'Change how the whole site looks: its colours, fonts, corner shapes and spacing. '
            .'This affects every page, not just the open one, and the operator has to apply it separately. '
            .'Pick a style by matching the words they used; only fine-tune an individual setting when they '
            .'named that setting themselves.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        $fields = [
            'preset' => $schema->string()
                ->description("The overall look, chosen by matching the operator's words against these: ".$this->presetGuide()
                    .' Omit to keep the current look and only fine-tune.')
                ->enum(array_map(static fn (StylePreset $preset): string => $preset->value, StylePreset::cases())),
            'align_layouts' => $schema->boolean()
                ->description('Re-lay every section on this page to the layouts the chosen style was designed with. '
                    .'Prefer this over changing sections one at a time. Ignored when no style is given.'),
        ];

        foreach (TokenKey::cases() as $key) {
            $fields[$key->value] = $schema->string()
                ->description($key->label().'. Only set this when the operator asked for this specific thing.')
                ->enum(array_keys(TokenOptions::for($key)));
        }

        return $fields;
    }

    public function handle(Request $request): string
    {
        $arguments = $request->toArray();

        $requestedPreset = $arguments['preset'] ?? null;
        $preset = is_string($requestedPreset) ? StylePreset::tryFrom($requestedPreset) : null;

        if (is_string($requestedPreset) && ! $preset instanceof StylePreset) {
            return sprintf(
                "There is no '%s' style, so nothing changed. The styles are: %s.\n\n%s",
                $requestedPreset,
                implode(', ', array_map(static fn (StylePreset $case): string => $case->value, StylePreset::cases())),
                $this->draft->outline(),
            );
        }

        $changes = TokenSelection::changes($arguments);
        $rejected = $this->rejectedTokens($changes);

        if ($rejected !== null) {
            return $rejected."\n\n".$this->draft->outline();
        }

        if (! $preset instanceof StylePreset && $changes === []) {
            return "No style was given, so nothing changed.\n\n".$this->draft->outline();
        }

        if ($preset instanceof StylePreset) {
            $this->style->applyPreset($preset);
        }

        if ($changes !== []) {
            $this->style->apply($changes);
        }

        $aligned = $preset instanceof StylePreset && ($arguments['align_layouts'] ?? false) === true;

        if ($aligned) {
            $this->draft->replace($this->alignedBlocks($preset));
        }

        return sprintf(
            "The site style is now %s%s. It is staged on the canvas only — tell the operator it affects every page and that they need to apply it.\n\n%s",
            $this->style->current()->describe(),
            $aligned ? ", and this page's section layouts were aligned to it" : '',
            $this->draft->outline(),
        );
    }

    /**
     * Every block re-laid to the preset's layouts, keeping the editor's keys.
     *
     * Assigns into `data`'s existing `variant` slot rather than rebuilding the
     * array — `AddPageBlock` writes the variant first, `pages.blocks` is `json`
     * so key order survives, and `RecordPageRevision` compares with `===`.
     *
     * @return list<array{key: string, type: string, data: array<string, mixed>}>
     */
    private function alignedBlocks(StylePreset $preset): array
    {
        $blocks = $this->draft->blocks();

        foreach ($blocks as $index => $block) {
            $variant = $this->stampVariantDefaults->variantFor($block['type'], $preset);

            if ($variant !== null) {
                $blocks[$index]['data'][BlockShape::VARIANT_KEY] = $variant;
            }
        }

        return $blocks;
    }

    /**
     * An enumerated selection has no partial form, so an unrecognised value
     * rejects the WHOLE call with the legal set rather than being dropped: a
     * silent drop leaves the model believing it succeeded, so its next call
     * builds on a false premise and its prose reports a change that never
     * happened.
     *
     * @param  array<string, string>  $changes
     */
    private function rejectedTokens(array $changes): ?string
    {
        foreach (TokenKey::cases() as $key) {
            $value = $changes[$key->value] ?? null;

            if ($value === null) {
                continue;
            }

            if ($key->tryValue($value) instanceof BackedEnum) {
                continue;
            }

            return sprintf(
                "'%s' is not a valid %s, so nothing changed. The options are: %s.",
                $value,
                mb_strtolower($key->label()),
                implode(', ', array_keys(TokenOptions::for($key))),
            );
        }

        return null;
    }

    /**
     * The preset menu the model matches an adjective against. Built from
     * {@see StylePreset::synonyms()} so the grounding lives on the enum, next
     * to the tokens it selects, rather than being restated in prose here.
     */
    private function presetGuide(): string
    {
        return implode(' ', array_map(
            static fn (StylePreset $preset): string => $preset->value.': '.implode(', ', $preset->synonyms()).'.',
            StylePreset::cases(),
        ));
    }
}
