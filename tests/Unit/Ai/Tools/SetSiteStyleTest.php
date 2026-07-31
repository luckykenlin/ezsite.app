<?php

declare(strict_types=1);

use App\Actions\Pages\StampVariantDefaults;
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
    return new SetSiteStyle($style, $draft, resolve(StampVariantDefaults::class));
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
        ->and($result)->toContain('section layouts were aligned');
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

it('rejects an unknown token value and lists the options, changing nothing', function (): void {
    $style = styleDraft();

    $result = styleTool($style, styleableDraft())->handle(new Request([
        'preset' => 'warm-craft',
        TokenKey::Radius->value => 'enormous',
    ]));

    expect($style->touched())->toBeFalse()
        ->and($result)->toContain("'enormous' is not a valid corner radius")
        ->and($result)->toContain('full');
});

it('reports doing nothing when given nothing', function (): void {
    $style = styleDraft();

    $result = styleTool($style, styleableDraft())->handle(new Request([]));

    expect($style->touched())->toBeFalse()
        ->and($result)->toContain('No style was given');
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
        // The load-bearing clause: this reaches pages the operator is not
        // looking at, and they have to be told.
        ->and($tool->description())->toContain('affects every page');
});
