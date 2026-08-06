<?php

declare(strict_types=1);

use App\Actions\Pages\StampPresetDefaults;
use App\Ai\PageDraft;
use App\Ai\SiteStyleDraft;
use App\Ai\Tools\SetSiteStyle;
use App\Design\ColorPalette;
use App\Design\DesignTokens;
use App\Design\RadiusScale;
use App\Design\StylePreset;
use App\Design\TokenKey;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

function styleDraft(): SiteStyleDraft
{
    return new SiteStyleDraft(DesignTokens::default());
}

function styleTool(SiteStyleDraft $style, PageDraft $draft): SetSiteStyle
{
    return new SetSiteStyle($style, $draft, resolve(StampPresetDefaults::class));
}

function styleableDraft(): PageDraft
{
    return new PageDraft([
        ['key' => 'k1', 'type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Hi']],
        ['key' => 'k2', 'type' => 'features', 'data' => ['variant' => 'grid']],
    ]);
}

it('stages a preset chosen by name', function (): void {
    $style = styleDraft();

    $result = styleTool($style, styleableDraft())->handle(new Request(['preset' => 'warm-craft']));

    expect($style->current()->preset)->toBe(StylePreset::WarmCraft)
        ->and($style->toArray())->toBe(StylePreset::WarmCraft->tokens()->toArray())
        // The model has to relay both facts, so the tool result states them.
        ->and($result)->toContain('affects every page')
        ->and($result)->toContain('staged on the canvas only');
});

it('fine-tunes a single token without a preset', function (): void {
    $style = styleDraft();

    styleTool($style, styleableDraft())->handle(new Request([TokenKey::Radius->value => RadiusScale::Full->value]));

    expect($style->current()->radius)->toBe(RadiusScale::Full)
        ->and($style->current()->palette)->toBe(ColorPalette::Default);
});

/*
 * The one-call restyle. Without align_layouts an eight-block page needs nine
 * tool calls against MaxSteps(12) and a 90-second budget enforced between stream
 * events — the difference between a broad restyle finishing and timing out. The
 * expansion is server-side and deterministic, not N model guesses.
 */
it('re-lays the page to the preset when asked to align layouts', function (): void {
    $draft = styleableDraft();
    $expected = StylePreset::BoldEditorial->blockVariantDefaults();

    $result = styleTool(styleDraft(), $draft)->handle(new Request([
        'preset' => 'bold-editorial',
        'align_layouts' => true,
    ]));

    expect($draft->blocks()[0]['data']['variant'])->toBe($expected['hero'])
        ->and($draft->blocks()[1]['data']['variant'])->toBe($expected['features'])
        // Copy is never collateral damage of a restyle.
        ->and($draft->blocks()[0]['data']['heading'])->toBe('Hi')
        ->and($result)->toContain('section layouts and backgrounds were aligned');
});

/*
 * A preset has two per-block halves, and aligning only one of them is the
 * failure this pins: layouts set to bold-editorial while the backgrounds still
 * say calm-coastal looks broken in a way neither setting explains.
 */
it('aligns section backgrounds in the same call, and leaves the hero to its variant', function (): void {
    $draft = styleableDraft();

    styleTool(styleDraft(), $draft)->handle(new Request([
        'preset' => 'bold-editorial',
        'align_layouts' => true,
    ]));

    expect($draft->blocks()[1]['data']['appearance'])
        ->toBe(StylePreset::BoldEditorial->blockAppearanceDefaults()['features'])
        // No preset says anything about a hero: its variant already decides its
        // weight, and full-bleed-overlay is dark by construction.
        ->and($draft->blocks()[0]['data'])->not->toHaveKey('appearance');
});

it('preserves the stored key order when it re-lays a block', function (): void {
    $draft = styleableDraft();
    $before = array_keys($draft->blocks()[0]['data']);

    styleTool(styleDraft(), $draft)->handle(new Request(['preset' => 'bold-editorial', 'align_layouts' => true]));

    expect(array_keys($draft->blocks()[0]['data']))->toBe($before);
});

it('leaves layouts alone unless alignment was asked for', function (): void {
    $draft = styleableDraft();

    styleTool(styleDraft(), $draft)->handle(new Request(['preset' => 'bold-editorial']));

    expect($draft->blocks())->toBe(styleableDraft()->blocks());
});

/*
 * An enumerated selection has no partial form. Dropping a bad value silently
 * would leave the model believing it succeeded — so its next call builds on a
 * false premise and its prose reports a change that never happened.
 */
it('rejects an unknown preset and lists the real ones, changing nothing', function (): void {
    $style = styleDraft();

    $result = styleTool($style, styleableDraft())->handle(new Request(['preset' => 'apple']));

    expect($style->touched())->toBeFalse()
        ->and($result)->toContain("There is no 'apple' style")
        ->and($result)->toContain('professional-minimal');
});

/*
 * Partial, not all-or-nothing: one bad value must not void the preset and six
 * good tokens with it — that burned a step of #[MaxSteps] on a re-send. But
 * the rejection is REPORTED, so the model never believes the bad value landed.
 */
it('applies the valid tokens and reports the one it rejected', function (): void {
    $style = styleDraft();

    $result = styleTool($style, styleableDraft())->handle(new Request([
        'preset' => 'warm-craft',
        TokenKey::Radius->value => 'enormous',
    ]));

    expect($style->touched())->toBeTrue()
        ->and($style->current()->palette)->toBe(StylePreset::WarmCraft->tokens()->palette)
        // The preset survives untuned — the bad radius was dropped, not zeroed.
        ->and($style->current()->preset)->toBe(StylePreset::WarmCraft)
        ->and($result)->toContain("'enormous' is not a valid corner radius and was not applied")
        ->and($result)->toContain('full');
});

/*
 * The palette rejection repeats the colour guide, not just the slugs: a model
 * that guessed "navy" needs the hue words to make its NEXT call the right one
 * instead of a second guess.
 */
it('answers a bad palette guess with the colour guide', function (): void {
    $style = styleDraft();

    $result = styleTool($style, styleableDraft())->handle(new Request([
        TokenKey::Palette->value => 'navy',
    ]));

    expect($style->touched())->toBeFalse()
        ->and($result)->toContain("'navy' is not a valid palette")
        ->and($result)->toContain('deep blue');
});

it('reports doing nothing when given nothing', function (): void {
    $style = styleDraft();

    $result = styleTool($style, styleableDraft())->handle(new Request([]));

    expect($style->touched())->toBeFalse()
        ->and($result)->toContain('No style was given');
});

/*
 * The exact-colour lever — the one place the assistant may hold a hex. A hex
 * implies the brand palette (a staged colour nobody renders is a change the
 * operator never sees), and everything stays staged behind Apply.
 */
it('stages a brand hex and switches the palette to brand with it', function (): void {
    $style = styleDraft();

    $result = styleTool($style, styleableDraft())->handle(new Request([
        'brand_primary' => '#1A2B3C',
    ]));

    expect($style->current()->palette)->toBe(ColorPalette::Brand)
        // Lower-cased by the validity gate, like the businesses columns store it.
        ->and($style->toArray()['brand_primary'])->toBe('#1a2b3c')
        ->and($result)->toContain('brand primary #1a2b3c')
        ->and($result)->toContain('staged on the canvas only');
});

it('rejects the whole call when a hex is not a colour', function (): void {
    $style = styleDraft();

    $result = styleTool($style, styleableDraft())->handle(new Request([
        'brand_primary' => 'deep blue',
        TokenKey::Radius->value => RadiusScale::Full->value,
    ]));

    // All-or-nothing, unlike the tokens: the colour IS the request, so
    // applying the radius half would report success on the half that failed.
    expect($style->touched())->toBeFalse()
        ->and($result)->toContain("'deep blue' is not a colour this builder can use")
        ->and($result)->toContain('six-digit hex');
});

/*
 * The silent-fallback trap: `palette: brand` with no primary anywhere renders
 * the Default palette while the reply claims a brand look. Refused with
 * directions instead.
 */
it('refuses the brand palette when no primary colour exists anywhere', function (): void {
    $style = styleDraft();

    $result = styleTool($style, styleableDraft())->handle(new Request([
        TokenKey::Palette->value => ColorPalette::Brand->value,
    ]));

    expect($style->touched())->toBeFalse()
        ->and($result)->toContain('needs a primary colour first')
        ->and($result)->toContain('business profile');
});

it('allows the brand palette when the business already has a primary', function (): void {
    $style = new SiteStyleDraft(DesignTokens::default(), savedBrand: ['brand_primary' => '#336699']);

    styleTool($style, styleableDraft())->handle(new Request([
        TokenKey::Palette->value => ColorPalette::Brand->value,
    ]));

    expect($style->current()->palette)->toBe(ColorPalette::Brand)
        // The saved hex is not re-staged — it is already on the row.
        ->and($style->toArray())->not->toHaveKey('brand_primary');
});

it('publishes the preset vocabulary and the blast radius in its schema', function (): void {
    $tool = styleTool(styleDraft(), styleableDraft());

    $serialized = json_decode(json_encode(array_map(
        fn ($type): array => $type->toArray(),
        $tool->schema(new JsonSchemaTypeFactory),
    )), associative: true);

    expect($serialized['preset']['enum'])->toHaveSameSize(StylePreset::cases())
        // The adjectives are the grounding — without them in the schema the
        // model is choosing among six opaque slugs.
        ->and($serialized['preset']['description'])->toContain('premium')
        ->and($serialized['align_layouts']['type'])->toBe('boolean')
        ->and($serialized)->toHaveKey(TokenKey::Density->value)
        // The palette's own grounding: colour words per slug, so "deep blue"
        // lands on ocean instead of a coin-flip among bare names.
        ->and($serialized[TokenKey::Palette->value]['description'])->toContain('deep blue')
        // The hex fields carry their narrow licence in the schema itself.
        ->and($serialized['brand_primary']['description'])->toContain('ONLY when the operator')
        // The load-bearing clause: this reaches pages the operator is not
        // looking at, and they have to be told.
        ->and($tool->description())->toContain('affects every page');
});
