<?php

declare(strict_types=1);

use App\Actions\Pages\StampPresetDefaults;
use App\Design\StylePreset;
use App\Site\Blocks\BlockType;
use App\Site\Blocks\BlockVocabulary;

function stampPresetDefaults(): StampPresetDefaults
{
    return resolve(StampPresetDefaults::class);
}

/*
 * Deliberately no "variantFor returns the preset's entry" test here. Production
 * is `in_array($preferred, $variants) ? $preferred : $variants[0]`, so asserting
 * it equals `$preset->blockVariantDefaults()[$type]` holds exactly when the
 * vocabulary contains that variant — and *that* is already
 * StylePresetTest's "only defaults block variants that exist in the vocabulary".
 * The plumbing from handle() down to the map is covered by the restamp tests
 * below, which key by type and so fail if the wrong entry is read.
 */

/*
 * The fallback branch, reached by a block type added AFTER the six presets were
 * authored — every current type has an entry, so the situation is built rather
 * than found. The alternative to falling back is stamping a variant the type
 * does not offer, which BlockRegistry then refuses to render: an amber
 * placeholder where a section used to be, on every page using that type.
 *
 * StylePresetTest asserts the entries that DO exist are valid; this covers the
 * day someone adds a type and forgets to add six.
 */
it('falls back to the first declared layout for a type the preset never named', function (): void {
    $vocabulary = new BlockVocabulary([
        'novel' => new BlockType(
            type: 'novel',
            description: 'A block type added after the presets were authored.',
            variants: ['first', 'second'],
            bind: null,
            icon: null,
            fields: [],
            sample: [],
        ),
    ]);

    expect(new StampPresetDefaults($vocabulary)->variantFor('novel', StylePreset::WarmCraft))->toBe('first');
});

it('has no layout to give a type with a single fixed view', function (): void {
    expect(stampPresetDefaults()->variantFor('heading', StylePreset::WarmCraft))->toBeNull();
});

it('has no layout to give a type it does not know', function (): void {
    // Unknown types genuinely reach a real page — it is why the render loop is
    // defensive — so a restyle must not be the thing that throws on one.
    expect(stampPresetDefaults()->variantFor('not-a-block', StylePreset::WarmCraft))->toBeNull();
});

it('restamps a whole list, leaving content and unknown types alone', function (): void {
    $expected = StylePreset::CalmCoastal->blockVariantDefaults();

    $blocks = stampPresetDefaults()->handle([
        ['type' => 'hero', 'data' => ['heading' => 'Keep me']],
        ['type' => 'heading', 'data' => ['content' => 'No variants here']],
        ['type' => 'not-a-block', 'data' => []],
    ], StylePreset::CalmCoastal);

    expect($blocks[0]['data']['variant'])->toBe($expected['hero'])
        ->and($blocks[0]['data']['heading'])->toBe('Keep me')
        ->and($blocks[1]['data'])->not->toHaveKey('variant')
        ->and($blocks[2]['data'])->not->toHaveKey('variant');
});

it('gives every declared type the appearance the preset was designed with', function (StylePreset $preset): void {
    foreach ($preset->blockAppearanceDefaults() as $type => $expected) {
        expect(stampPresetDefaults()->appearanceFor($type, $preset))->toBe($expected);
    }
})->with(StylePreset::cases());

/*
 * Absent is NOT "reset", and this is the distinction that keeps a restyle from
 * being destructive beyond what the operator asked for. A preset with no opinion
 * about a type must leave the block exactly as it found it — including a
 * background the operator set by hand — because the alternative is that
 * "make it warmer" silently discards a deliberate choice somewhere down the page.
 */
it('leaves a block alone when the preset says nothing about its type', function (): void {
    // professional-minimal deliberately names no cta, prose, hero or heading.
    $stamped = stampPresetDefaults()->stamp(
        ['appearance' => ['tone' => 'accent'], 'heading' => 'Book now'],
        'cta',
        StylePreset::ProfessionalMinimal,
    );

    expect($stamped['appearance'])->toBe(['tone' => 'accent'])
        ->and($stamped['heading'])->toBe('Book now');
});

it('overwrites an appearance the preset does have an opinion about', function (): void {
    $stamped = stampPresetDefaults()->stamp(
        ['appearance' => ['tone' => 'accent', 'spacing' => 'flush']],
        'gallery',
        StylePreset::BoldEditorial,
    );

    // Wholesale, not merged: the preset's entry is the complete intent for that
    // section, so a leftover `flush` from the previous look must not survive
    // into a rhythm that was designed with `tight`.
    expect($stamped['appearance'])->toBe(['tone' => 'inverted', 'spacing' => 'tight']);
});

it('stamps both halves of a preset over a whole list', function (): void {
    $blocks = stampPresetDefaults()->handle([
        ['type' => 'features', 'data' => ['heading' => 'Keep me']],
        ['type' => 'heading', 'data' => ['content' => 'No appearance for me']],
    ], StylePreset::CalmCoastal);

    expect($blocks[0]['data']['variant'])->toBe(StylePreset::CalmCoastal->blockVariantDefaults()['features'])
        ->and($blocks[0]['data']['appearance'])->toBe(StylePreset::CalmCoastal->blockAppearanceDefaults()['features'])
        ->and($blocks[0]['data']['heading'])->toBe('Keep me')
        ->and($blocks[1]['data'])->not->toHaveKey('appearance');
});

/*
 * fill() — the BACK-FILL semantics, used only by GenerateSiteDraft. The model's
 * validated layout choices survive; the preset speaks where the model was
 * silent; and the fallback is position-aware, so even a silent model gets an
 * alternating rhythm instead of the flat per-type stack. handle()/stamp() above
 * keep their overwrite semantics — that pair belongs to the restyle path.
 */
it('fill: keeps a valid model variant and tone, filling only the missing dimension', function (): void {
    $blocks = stampPresetDefaults()->fill([
        ['type' => 'features', 'data' => ['variant' => 'grid', 'appearance' => ['tone' => 'accent']]],
    ], StylePreset::WarmCraft);

    // WarmCraft says list + muted/airy; the model said grid + accent. Grid and
    // accent survive, and only the unset spacing takes the preset's airy.
    expect($blocks[0]['data']['variant'])->toBe('grid')
        ->and($blocks[0]['data']['appearance'])->toBe(['tone' => 'accent', 'spacing' => 'airy']);
});

it('fill: replaces an invalid variant with the preset default', function (): void {
    $blocks = stampPresetDefaults()->fill([
        ['type' => 'features', 'data' => ['variant' => 'no-such-layout']],
    ], StylePreset::WarmCraft);

    expect($blocks[0]['data']['variant'])->toBe('alternating');
});

it('fill: alternates a repeating base band instead of stacking it', function (): void {
    // professional-minimal deliberately says base for every content type — the
    // per-type map cannot alternate, but the position-aware walk can.
    $blocks = stampPresetDefaults()->fill([
        ['type' => 'features', 'data' => []],
        ['type' => 'offerings', 'data' => []],
        ['type' => 'gallery', 'data' => []],
    ], StylePreset::ProfessionalMinimal);

    expect($blocks[0]['data']['appearance'])->toBe(['tone' => 'base'])
        // The preset's axis opinions ride along with the flipped tone: this is
        // where professional-minimal keeps its old priced-list offerings look.
        ->and($blocks[1]['data']['appearance'])->toBe(['tone' => 'muted', 'width' => 'narrow', 'align' => 'start', 'columns' => 'one', 'item_style' => 'plain'])
        ->and($blocks[2]['data']['appearance'])->toBe(['tone' => 'base']);
});

it('fill: demotes a dark band that would sit directly under another dark band', function (): void {
    // bold-editorial puts gallery AND testimonials on inverted; adjacent, the
    // second becomes muted — never two dark bands next to each other.
    $blocks = stampPresetDefaults()->fill([
        ['type' => 'gallery', 'data' => []],
        ['type' => 'testimonials', 'data' => []],
    ], StylePreset::BoldEditorial);

    expect($blocks[0]['data']['appearance'])->toBe(['tone' => 'inverted', 'spacing' => 'tight'])
        ->and($blocks[1]['data']['appearance'])->toBe(['tone' => 'muted']);
});

it('fill: a model tone resets the alternation state', function (): void {
    $blocks = stampPresetDefaults()->fill([
        ['type' => 'features', 'data' => ['appearance' => ['tone' => 'inverted']]],
        ['type' => 'contact', 'data' => []],
    ], StylePreset::WarmCraft);

    // Contact's preset muted does not repeat anything — the previous band was
    // the model's inverted — so it lands unflipped. The model's tone survives
    // on features while the preset still fills its unset spacing.
    expect($blocks[0]['data']['appearance'])->toBe(['tone' => 'inverted', 'spacing' => 'airy'])
        ->and($blocks[1]['data']['appearance'])->toBe(['tone' => 'muted', 'spacing' => 'airy']);
});

it('fill: writes nothing over silence, and a spacing-only preset entry stays spacing-only', function (): void {
    // steps has no WarmCraft entry and no flip is needed first in the list, so
    // it keeps its view's own default; prose has a spacing-only entry.
    $blocks = stampPresetDefaults()->fill([
        ['type' => 'steps', 'data' => []],
        ['type' => 'prose', 'data' => []],
    ], StylePreset::WarmCraft);

    expect($blocks[0]['data'])->not->toHaveKey('appearance')
        // steps counted as base, so prose (also no tone opinion) flips to muted
        // to keep two silent base bands from stacking — plus its preset airy.
        ->and($blocks[1]['data']['appearance'])->toBe(['tone' => 'muted', 'spacing' => 'airy']);
});

it('fill: skips hero and heading appearance, and leaves unknown types alone', function (): void {
    $blocks = stampPresetDefaults()->fill([
        ['type' => 'hero', 'data' => []],
        ['type' => 'heading', 'data' => ['content' => 'Divider']],
        ['type' => 'not-a-block', 'data' => ['keep' => 'me']],
        ['type' => 'features', 'data' => []],
    ], StylePreset::WarmCraft);

    // The hero still gets its variant filled — only its appearance is exempt —
    // and it does not advance the alternation, so features lands its preset
    // muted unflipped, alternating against the first real band.
    expect($blocks[0]['data']['variant'])->toBe('left-text-right-image')
        ->and($blocks[0]['data'])->not->toHaveKey('appearance')
        ->and($blocks[1]['data'])->not->toHaveKey('variant')
        ->and($blocks[1]['data'])->not->toHaveKey('appearance')
        ->and($blocks[2]['data'])->toBe(['keep' => 'me'])
        ->and($blocks[3]['data']['appearance'])->toBe(['tone' => 'muted', 'spacing' => 'airy']);
});

it('fill: a valid model axis choice survives the preset opinion', function (): void {
    // professional-minimal's offerings entry says columns=one/plain; the model
    // chose two-column cards, and the model wins on every axis it spoke to.
    $blocks = stampPresetDefaults()->fill([
        ['type' => 'offerings', 'data' => ['appearance' => ['columns' => 'two', 'item_style' => 'card']]],
    ], StylePreset::ProfessionalMinimal);

    $appearance = $blocks[0]['data']['appearance'];

    expect($appearance['columns'])->toBe('two')
        ->and($appearance['item_style'])->toBe('card')
        // The axes the model was silent on still take the preset's opinion.
        ->and($appearance['width'])->toBe('narrow')
        ->and($appearance['align'])->toBe('start');
});
