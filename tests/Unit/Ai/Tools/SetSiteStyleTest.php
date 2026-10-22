<?php

declare(strict_types=1);

use App\Actions\Pages\StampSchemeDefaults;
use App\Ai\PageDraft;
use App\Ai\SiteStyleDraft;
use App\Ai\Tools\SetSiteStyle;
use App\Design\ColorPalette;
use App\Design\DesignTokens;
use App\Themes\CurrentTheme;
use App\Themes\Definitions\DefaultTheme;
use App\Themes\Theme;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

function styleDraft(): SiteStyleDraft
{
    return new SiteStyleDraft(DesignTokens::default());
}

function styleTool(SiteStyleDraft $style, PageDraft $draft): SetSiteStyle
{
    return new SetSiteStyle($style, $draft, resolve(StampSchemeDefaults::class), resolve(CurrentTheme::class));
}

function styleableDraft(): PageDraft
{
    return new PageDraft([
        ['key' => 'k1', 'type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Hi']],
        ['key' => 'k2', 'type' => 'features', 'data' => ['variant' => 'grid']],
    ]);
}

it('stages a scheme chosen by name', function (): void {
    $style = styleDraft();

    $result = styleTool($style, styleableDraft())->handle(new Request(['scheme' => 'warm-craft']));

    expect($style->current()->scheme)->toBe(DefaultTheme::WARM_CRAFT)
        ->and($style->toArray())->toBe(defaultScheme(DefaultTheme::WARM_CRAFT)->tokens->toArray())
        // The model has to relay both facts, so the tool result states them.
        ->and($result)->toContain('affects every page')
        ->and($result)->toContain('staged on the canvas only');
});

/*
 * The one-call restyle. Without align_layouts an eight-block page needs nine
 * tool calls against MaxSteps(12) and a 90-second budget enforced between stream
 * events — the difference between a broad restyle finishing and timing out. The
 * expansion is server-side and deterministic, not N model guesses.
 */
it('re-lays the page to the scheme when asked to align layouts', function (): void {
    $draft = styleableDraft();
    $expected = defaultScheme(DefaultTheme::BOLD_EDITORIAL)->blockVariantDefaults;

    $result = styleTool(styleDraft(), $draft)->handle(new Request([
        'scheme' => 'bold-editorial',
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
        'scheme' => 'bold-editorial',
        'align_layouts' => true,
    ]));

    expect($draft->blocks()[1]['data']['appearance'])
        ->toBe(defaultScheme(DefaultTheme::BOLD_EDITORIAL)->blockAppearanceDefaults['features'])
        // No preset says anything about a hero: its variant already decides its
        // weight, and full-bleed-overlay is dark by construction.
        ->and($draft->blocks()[0]['data'])->not->toHaveKey('appearance');
});

it('preserves the stored key order when it re-lays a block', function (): void {
    $draft = styleableDraft();
    $before = array_keys($draft->blocks()[0]['data']);

    styleTool(styleDraft(), $draft)->handle(new Request(['scheme' => 'bold-editorial', 'align_layouts' => true]));

    expect(array_keys($draft->blocks()[0]['data']))->toBe($before);
});

it('leaves layouts alone unless alignment was asked for', function (): void {
    $draft = styleableDraft();

    styleTool(styleDraft(), $draft)->handle(new Request(['scheme' => 'bold-editorial']));

    expect($draft->blocks())->toBe(styleableDraft()->blocks());
});

/*
 * An enumerated selection has no partial form. Dropping a bad value silently
 * would leave the model believing it succeeded — so its next call builds on a
 * false premise and its prose reports a change that never happened.
 */
it('rejects an unknown scheme and lists the real ones, changing nothing', function (): void {
    $style = styleDraft();

    $result = styleTool($style, styleableDraft())->handle(new Request(['scheme' => 'apple']));

    expect($style->touched())->toBeFalse()
        ->and($result)->toContain("There is no 'apple' style")
        ->and($result)->toContain('professional-minimal');
});

it('reports doing nothing when given nothing', function (): void {
    $style = styleDraft();

    $result = styleTool($style, styleableDraft())->handle(new Request([]));

    expect($style->touched())->toBeFalse()
        ->and($result)->toContain('No style was given');
});

/*
 * The exact-colour lever — the one place the assistant may hold a hex, and
 * everything stays staged behind Apply.
 */
it('stages a brand hex against the look in force, without changing the style', function (): void {
    $style = styleDraft();

    $result = styleTool($style, styleableDraft())->handle(new Request([
        'brand_primary' => '#1A2B3C',
    ]));

    // No palette switch: the hex shows once the site is on a brand-palette
    // scheme, and the tool has no lever to move one axis on its own.
    expect($style->current()->palette)->toBe(ColorPalette::Default)
        ->and($style->current()->scheme)->toBeNull()
        // Lower-cased by the validity gate, like the businesses columns store it.
        ->and($style->toArray()['brand_primary'])->toBe('#1a2b3c')
        ->and($result)->toContain('brand primary #1a2b3c')
        ->and($result)->toContain('staged on the canvas only');
});

it('stages a scheme and a brand hex together in one call', function (): void {
    $style = styleDraft();

    $result = styleTool($style, styleableDraft())->handle(new Request([
        'scheme' => 'warm-craft',
        'brand_primary' => '#1a2b3c',
    ]));

    expect($style->current()->scheme)->toBe(DefaultTheme::WARM_CRAFT)
        ->and($style->toArray()['brand_primary'])->toBe('#1a2b3c')
        ->and($result)->toContain('warm-craft')
        ->and($result)->toContain('with brand primary #1a2b3c');
});

it('rejects the whole call when a hex is not a colour', function (): void {
    $style = styleDraft();

    $result = styleTool($style, styleableDraft())->handle(new Request([
        'scheme' => 'warm-craft',
        'brand_primary' => 'deep blue',
    ]));

    // All-or-nothing: the colour IS the request, so applying the scheme half
    // would report success on the half that failed.
    expect($style->touched())->toBeFalse()
        ->and($result)->toContain("'deep blue' is not a colour this builder can use")
        ->and($result)->toContain('six-digit hex');
});

it('publishes the scheme vocabulary and the blast radius in its schema', function (): void {
    $tool = styleTool(styleDraft(), styleableDraft());

    $serialized = json_decode(json_encode(array_map(
        fn ($type): array => $type->toArray(),
        $tool->schema(new JsonSchemaTypeFactory),
    )), associative: true);

    expect($serialized['scheme']['enum'])->toHaveSameSize(Theme::Default->definition()->schemes)
        // The adjectives are the grounding — without them in the schema the
        // model is choosing among six opaque slugs.
        ->and($serialized['scheme']['description'])->toContain('premium')
        ->and($serialized['align_layouts']['type'])->toBe('boolean')
        // A scheme and the brand hexes are the whole surface: no per-axis field
        // for the model to reach for.
        ->and(array_keys($serialized))->toBe(['scheme', 'align_layouts', 'brand_primary', 'brand_secondary', 'brand_accent'])
        // The hex fields carry their narrow licence, and where they show, in
        // the schema itself.
        ->and($serialized['brand_primary']['description'])->toContain('ONLY when the operator')
        ->and($serialized['brand_primary']['description'])->toContain('built on the brand palette')
        // The load-bearing clauses: this reaches pages the operator is not
        // looking at, they have to be told, and there is nothing to fine-tune.
        ->and($tool->description())->toContain('affects every page')
        ->and($tool->description())->toContain('the operator has to apply it separately')
        ->and($tool->description())->toContain('no individual settings');
});
