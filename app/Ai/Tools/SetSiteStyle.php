<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Actions\Pages\StampSchemeDefaults;
use App\Ai\PageDraft;
use App\Ai\SiteStyleDraft;
use App\Design\ColorPalette;
use App\Design\TokenSelection;
use App\Themes\CurrentTheme;
use App\Themes\Scheme;
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
 * The model picks a designed scheme, never an individual setting. A scheme is
 * a combination that was designed together — the guard rail against jarring
 * free-form variant × token combinations — so an adjective resolves to a
 * scheme and that is the whole vocabulary; the per-axis fields this tool used
 * to carry ("rounder corners", "a different font") were the way a look nobody
 * designed got onto a live site, and they are gone. The menu is the current
 * theme's ({@see Scheme}), so the model is only ever offered looks the
 * tenant's theme can draw. The brand hexes are the one exact-colour lever,
 * and they show only on a look built on the brand palette — which is why a
 * hex aimed at any other look is refused rather than staged.
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
        private StampSchemeDefaults $stampSchemeDefaults,
        private CurrentTheme $theme,
    ) {
        //
    }

    public function description(): string
    {
        return 'Change how the whole site looks by picking one of its designed styles — each sets the colours, '
            .'fonts, corner shapes and spacing together — and, for an exact colour the operator named, its brand colours. '
            .'This affects every page, not just the open one, and the operator has to apply it separately. '
            .'Pick the style by matching the words they used; there are no individual settings to fine-tune.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        $fields = [
            'scheme' => $schema->string()
                ->description("The overall look, chosen by matching the operator's words against these: ".$this->schemeGuide()
                    .' Omit to keep the current look and only set brand colours.')
                ->enum(array_keys($this->theme->definition()->schemes)),
            'align_layouts' => $schema->boolean()
                ->description('Re-lay every section on this page to the layouts AND section backgrounds the chosen '
                    .'style was designed with. Prefer this over changing sections one at a time — a style whose '
                    .'layouts are applied but whose backgrounds are not looks worse than either alone. '
                    .'Ignored when no style is given.'),
        ];

        // The exact-colour lever. Free-form by necessity — a specific colour
        // has no enum — but validated against the same hex pattern that guards
        // the businesses columns, and staged like every other design change.
        foreach (TokenSelection::BRAND_KEYS as $key) {
            $fields[$key] = $schema->string()
                ->description(Str::headline($key).' as a six-digit hex like #1a2b3c. ONLY when the operator '
                    .'asked for that specific colour ("make the main colour deep blue", a hex they pasted). '
                    .'Brand colours only show on a style built on the brand palette and do not change the style itself; '
                    .'on any other style they are refused and nothing changes.');
        }

        return $fields;
    }

    public function handle(Request $request): string
    {
        $arguments = $request->toArray();

        $definition = $this->theme->definition();
        $requestedScheme = $arguments['scheme'] ?? null;
        $scheme = is_string($requestedScheme) ? $definition->scheme($requestedScheme) : null;

        if (is_string($requestedScheme) && ! $scheme instanceof Scheme) {
            return $this->draft->reply(sprintf(
                "There is no '%s' style, so nothing changed. The styles are: %s.",
                $requestedScheme,
                implode(', ', array_keys($definition->schemes)),
            ));
        }

        // The hexes are all-or-nothing: a specific colour IS the request, so
        // applying half of it would report success on the half that failed.
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

        if (! $scheme instanceof Scheme && $brand === []) {
            return $this->draft->reply('No style was given, so nothing changed.');
        }

        // Brand hexes only paint a look built on the brand palette. On any
        // other they would be staged, saved and never seen — a silent no-op
        // the model would still report as done — so the call is refused whole,
        // judged against the look it would land on.
        $landsOn = $scheme instanceof Scheme ? $scheme->tokens : $this->style->current();

        if ($brand !== [] && $landsOn->palette !== ColorPalette::Brand) {
            return $this->draft->reply($this->brandRefusal($definition->schemes));
        }

        if ($scheme instanceof Scheme) {
            $this->style->applyScheme($scheme);
        }

        if ($brand !== []) {
            $this->style->stageBrand($brand);
        }

        $aligned = $scheme instanceof Scheme && ($arguments['align_layouts'] ?? false) === true;

        if ($aligned) {
            $this->draft->replace($this->alignedBlocks($scheme));
        }

        return $this->draft->reply(sprintf(
            'The site style is now %s%s%s. It is staged on the canvas only — tell the operator it affects every page and that they need to apply it.',
            $this->style->current()->describe(),
            isset($brand['brand_primary']) ? ' with brand primary '.$brand['brand_primary'] : '',
            $aligned ? ", and this page's section layouts and backgrounds were aligned to it" : '',
        ));
    }

    /**
     * Every block re-laid to the scheme's layouts AND section backgrounds,
     * keeping the editor's keys.
     *
     * The per-block rule lives in {@see StampSchemeDefaults::stamp()}; only the
     * iteration is here, because PHPStan's array shapes are sealed and the draft's
     * `{key, type, data}` is not a subtype of the `{type, data}` that action's
     * `handle()` takes.
     *
     * @return list<array{key: string, type: string, data: array<string, mixed>}>
     */
    private function alignedBlocks(Scheme $scheme): array
    {
        $blocks = $this->draft->blocks();

        foreach ($blocks as $index => $block) {
            $blocks[$index]['data'] = $this->stampSchemeDefaults->stamp(
                $block['data'],
                $block['type'],
                $scheme,
            );
        }

        return $blocks;
    }

    /**
     * Why brand hexes changed nothing, and where they would: the theme's
     * brand-palette schemes by slug, or the plain fact that it has none — so
     * the model can offer a real alternative rather than retry the same call.
     *
     * @param  array<string, Scheme>  $schemes
     */
    private function brandRefusal(array $schemes): string
    {
        $brandSchemes = array_keys(array_filter(
            $schemes,
            static fn (Scheme $scheme): bool => $scheme->tokens->palette === ColorPalette::Brand,
        ));

        return 'Nothing changed: this style does not use brand colours, so they would not show. '
            .($brandSchemes === []
                ? 'None of the styles in this theme uses brand colours.'
                : 'The styles that do are: '.implode(', ', $brandSchemes).'.');
    }

    /**
     * The scheme menu the model matches an adjective against. Built from the
     * current theme's {@see Scheme::$synonyms} so the grounding lives on the
     * scheme, next to the tokens it selects, rather than being restated in
     * prose here.
     */
    private function schemeGuide(): string
    {
        return implode(' ', array_map(
            static fn (Scheme $scheme): string => $scheme->slug.': '.implode(', ', $scheme->synonyms).'.',
            array_values($this->theme->definition()->schemes),
        ));
    }
}
