<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Actions\Pages\StampPresetDefaults;
use App\Ai\PageDraft;
use App\Ai\SiteStyleDraft;
use App\Design\ColorPalette;
use App\Design\StylePreset;
use App\Design\TokenKey;
use App\Design\TokenOptions;
use App\Design\TokenSelection;
use BackedEnum;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
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
        private StampPresetDefaults $stampPresetDefaults,
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
                ->description('Re-lay every section on this page to the layouts AND section backgrounds the chosen '
                    .'style was designed with. Prefer this over changing sections one at a time — a style whose '
                    .'layouts are applied but whose backgrounds are not looks worse than either alone. '
                    .'Ignored when no style is given.'),
        ];

        foreach (TokenKey::cases() as $key) {
            $fields[$key->value] = $schema->string()
                ->description($key === TokenKey::Palette
                    // The palette gets colour words, not just its label: "make it
                    // deep blue" is the request this tool most often fails, and a
                    // bare slug list gives the model nothing to match "blue" against.
                    ? 'Palette. Only set this when the operator asked for a colour, matching their words against: '.$this->paletteGuide()
                    : $key->label().'. Only set this when the operator asked for this specific thing.')
                ->enum(array_keys(TokenOptions::for($key)));
        }

        // The exact-colour lever. Free-form by necessity — a specific colour
        // has no enum — but validated against the same hex pattern that guards
        // the businesses columns, and staged like every other design change.
        foreach (TokenSelection::BRAND_KEYS as $key) {
            $fields[$key] = $schema->string()
                ->description(Str::headline($key).' as a six-digit hex like #1a2b3c. ONLY when the operator '
                    .'asked for that specific colour ("make the main colour deep blue", a hex they pasted). '
                    .'Setting any brand colour switches the palette to brand.');
        }

        return $fields;
    }

    public function handle(Request $request): string
    {
        $arguments = $request->toArray();

        $requestedPreset = $arguments['preset'] ?? null;
        $preset = is_string($requestedPreset) ? StylePreset::tryFrom($requestedPreset) : null;

        if (is_string($requestedPreset) && ! $preset instanceof StylePreset) {
            return $this->draft->reply(sprintf(
                "There is no '%s' style, so nothing changed. The styles are: %s.",
                $requestedPreset,
                implode(', ', array_map(static fn (StylePreset $case): string => $case->value, StylePreset::cases())),
            ));
        }

        ['accepted' => $changes, 'rejected' => $rejected] = $this->partitionTokens(TokenSelection::changes($arguments));

        // The hexes are all-or-nothing, unlike the tokens: a specific colour IS
        // the request, so applying half of it would report success on the half
        // that failed.
        $brand = [];

        foreach (TokenSelection::BRAND_KEYS as $key) {
            $value = $arguments[$key] ?? null;

            if (! is_string($value) || $value === '') {
                continue;
            }

            $hex = ColorPalette::validHex($value);

            if ($hex === null) {
                return $this->draft->reply(sprintf(
                    "'%s' is not a colour this builder can use, so nothing changed. Brand colours are six-digit hex like #1a2b3c.",
                    $value,
                ));
            }

            $brand[$key] = $hex;
        }

        // A hex implies the brand palette — a staged colour nobody renders is a
        // change the model would report and the operator would never see.
        if ($brand !== []) {
            $changes[TokenKey::Palette->value] = ColorPalette::Brand->value;
        }

        // The silent-fallback trap: `palette: brand` with no primary anywhere
        // renders the Default palette while the reply claims a brand look.
        // Refuse it with directions instead.
        if (($changes[TokenKey::Palette->value] ?? null) === ColorPalette::Brand->value
            && ($brand['brand_primary'] ?? $this->style->brand()['brand_primary'] ?? null) === null) {
            return $this->draft->reply(
                'The brand palette needs a primary colour first, so nothing changed. '
                .'Give brand_primary as a hex, or tell the operator to set their brand colours on the business profile.',
            );
        }

        if (! $preset instanceof StylePreset && $changes === []) {
            return $this->draft->reply($rejected === []
                ? 'No style was given, so nothing changed.'
                : 'Nothing changed. '.implode(' ', $rejected));
        }

        if ($preset instanceof StylePreset) {
            $this->style->applyPreset($preset);
        }

        if ($brand !== []) {
            $this->style->stageBrand($brand);
        }

        if ($changes !== []) {
            $this->style->apply($changes);
        }

        $aligned = $preset instanceof StylePreset && ($arguments['align_layouts'] ?? false) === true;

        if ($aligned) {
            $this->draft->replace($this->alignedBlocks($preset));
        }

        return $this->draft->reply(sprintf(
            'The site style is now %s%s%s.%s It is staged on the canvas only — tell the operator it affects every page and that they need to apply it.',
            $this->style->current()->describe(),
            isset($brand['brand_primary']) ? ' with brand primary '.$brand['brand_primary'] : '',
            $aligned ? ", and this page's section layouts and backgrounds were aligned to it" : '',
            $rejected === [] ? '' : ' '.implode(' ', $rejected),
        ));
    }

    /**
     * Every block re-laid to the preset's layouts AND section backgrounds,
     * keeping the editor's keys.
     *
     * The per-block rule lives in {@see StampPresetDefaults::stamp()}; only the
     * iteration is here, because PHPStan's array shapes are sealed and the draft's
     * `{key, type, data}` is not a subtype of the `{type, data}` that action's
     * `handle()` takes.
     *
     * @return list<array{key: string, type: string, data: array<string, mixed>}>
     */
    private function alignedBlocks(StylePreset $preset): array
    {
        $blocks = $this->draft->blocks();

        foreach ($blocks as $index => $block) {
            $blocks[$index]['data'] = $this->stampPresetDefaults->stamp(
                $block['data'],
                $block['type'],
                $preset,
            );
        }

        return $blocks;
    }

    /**
     * Split the requested tokens into the ones that can apply and one line of
     * report per one that cannot. Partial, not all-or-nothing: one bad value
     * used to void the whole call and burn a step of `#[MaxSteps]` re-sending
     * six good ones — but the rejection is REPORTED, never silently dropped,
     * because a model that believes an unrecognised value landed builds its
     * next call on a false premise.
     *
     * @param  array<string, string>  $changes
     * @return array{accepted: array<string, string>, rejected: list<string>}
     */
    private function partitionTokens(array $changes): array
    {
        $accepted = [];
        $rejected = [];

        foreach (TokenKey::cases() as $key) {
            $value = $changes[$key->value] ?? null;

            if ($value === null) {
                continue;
            }

            if ($key->tryValue($value) instanceof BackedEnum) {
                $accepted[$key->value] = $value;

                continue;
            }

            $rejected[] = sprintf(
                "'%s' is not a valid %s and was not applied — the options are: %s.",
                $value,
                mb_strtolower($key->label()),
                // The palette rejection repeats the colour guide, not just the
                // slugs: a model that guessed "navy" needs the hue words to make
                // its NEXT call the right one instead of a second guess.
                $key === TokenKey::Palette
                    ? $this->paletteGuide()
                    : implode(', ', array_keys(TokenOptions::for($key))),
            );
        }

        return ['accepted' => $accepted, 'rejected' => $rejected];
    }

    /**
     * The palette menu the model matches a colour word against — the colour
     * counterpart of {@see presetGuide()}, grounded on the enum for the same
     * reason.
     */
    private function paletteGuide(): string
    {
        return implode(' ', array_map(
            static fn (ColorPalette $palette): string => $palette->value.': '.$palette->guide().'.',
            ColorPalette::cases(),
        ));
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
