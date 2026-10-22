<?php

declare(strict_types=1);

use App\Actions\Pages\StampSchemeDefaults;
use App\Site\Blocks\BlockType;
use App\Site\Blocks\BlockVocabulary;
use App\Themes\Definitions\DefaultTheme;
use App\Themes\Scheme;
use App\Themes\Theme;

function stampSchemeDefaults(): StampSchemeDefaults
{
    return resolve(StampSchemeDefaults::class);
}

/*
 * Deliberately no "variantFor returns the scheme's entry" test here. Production
 * is `in_array($preferred, $variants) ? $preferred : $variants[0]`, so asserting
 * it equals `$scheme->blockVariantDefaults[$type]` holds exactly when the
 * vocabulary contains that variant — and *that* is already ThemeTest's
 * "every scheme gives every variant-bearing block of its theme a default".
 * The plumbing from handle() down to the map is covered by the restamp tests
 * below, which key by type and so fail if the wrong entry is read.
 */

/*
 * The fallback branch, reached by a block type added AFTER the schemes were
 * authored — every current type has an entry, so the situation is built rather
 * than found. The alternative to falling back is stamping a variant the type
 * does not offer, which BlockRegistry then refuses to render: an amber
 * placeholder where a section used to be, on every page using that type.
 *
 * ThemeTest asserts the entries that DO exist are valid; this covers the
 * day someone adds a type and forgets to add eight.
 */
it('falls back to the first declared layout for a type the scheme never named', function (): void {
    $vocabulary = new BlockVocabulary([
        'novel' => new BlockType(
            type: 'novel',
            description: 'A block type added after the schemes were authored.',
            variants: ['first', 'second'],
            bind: null,
            icon: null,
            fields: [],
            sample: [],
        ),
    ]);

    expect(new StampSchemeDefaults($vocabulary)->variantFor('novel', defaultScheme(DefaultTheme::WARM_CRAFT)))->toBe('first');
});

it('has no layout to give a type with a single fixed view', function (): void {
    expect(stampSchemeDefaults()->variantFor('heading', defaultScheme(DefaultTheme::WARM_CRAFT)))->toBeNull();
});

it('has no layout to give a type it does not know', function (): void {
    // Unknown types genuinely reach a real page — it is why the render loop is
    // defensive — so a restyle must not be the thing that throws on one.
    expect(stampSchemeDefaults()->variantFor('not-a-block', defaultScheme(DefaultTheme::WARM_CRAFT)))->toBeNull();
});

it('restamps a whole list, leaving content and unknown types alone', function (): void {
    $expected = defaultScheme(DefaultTheme::CALM_COASTAL)->blockVariantDefaults;

    $blocks = stampSchemeDefaults()->handle([
        ['type' => 'hero', 'data' => ['heading' => 'Keep me']],
        ['type' => 'heading', 'data' => ['content' => 'No variants here']],
        ['type' => 'not-a-block', 'data' => []],
    ], defaultScheme(DefaultTheme::CALM_COASTAL));

    expect($blocks[0]['data']['variant'])->toBe($expected['hero'])
        ->and($blocks[0]['data']['heading'])->toBe('Keep me')
        ->and($blocks[1]['data'])->not->toHaveKey('variant')
        ->and($blocks[2]['data'])->not->toHaveKey('variant');
});

it('gives every declared type the appearance the scheme was designed with', function (Scheme $scheme): void {
    foreach ($scheme->blockAppearanceDefaults as $type => $expected) {
        expect(stampSchemeDefaults()->appearanceFor($type, $scheme))->toBe($expected);
    }
})->with(Theme::Default->definition()->schemes);

/*
 * Absent is NOT "reset", and this is the distinction that keeps a restyle from
 * being destructive beyond what the operator asked for. A preset with no opinion
 * about a type must leave the block exactly as it found it — including a
 * background the operator set by hand — because the alternative is that
 * "make it warmer" silently discards a deliberate choice somewhere down the page.
 */
it('leaves a block alone when the scheme says nothing about its type', function (): void {
    // professional-minimal deliberately names no cta, prose, hero or heading.
    $stamped = stampSchemeDefaults()->stamp(
        ['appearance' => ['tone' => 'accent'], 'heading' => 'Book now'],
        'cta',
        defaultScheme(DefaultTheme::PROFESSIONAL_MINIMAL),
    );

    expect($stamped['appearance'])->toBe(['tone' => 'accent'])
        ->and($stamped['heading'])->toBe('Book now');
});

it('overwrites an appearance the scheme does have an opinion about', function (): void {
    $stamped = stampSchemeDefaults()->stamp(
        ['appearance' => ['tone' => 'accent', 'spacing' => 'flush']],
        'gallery',
        defaultScheme(DefaultTheme::BOLD_EDITORIAL),
    );

    // Wholesale, not merged: the preset's entry is the complete intent for that
    // section, so a leftover `flush` from the previous look must not survive
    // into a rhythm that was designed with `tight`.
    expect($stamped['appearance'])->toBe(['tone' => 'inverted', 'spacing' => 'tight', 'width' => 'full']);
});

it('stamps both halves of a scheme over a whole list', function (): void {
    $blocks = stampSchemeDefaults()->handle([
        ['type' => 'features', 'data' => ['heading' => 'Keep me']],
        ['type' => 'heading', 'data' => ['content' => 'No appearance for me']],
    ], defaultScheme(DefaultTheme::CALM_COASTAL));

    expect($blocks[0]['data']['variant'])->toBe(defaultScheme(DefaultTheme::CALM_COASTAL)->blockVariantDefaults['features'])
        ->and($blocks[0]['data']['appearance'])->toBe(defaultScheme(DefaultTheme::CALM_COASTAL)->blockAppearanceDefaults['features'])
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
    $blocks = stampSchemeDefaults()->fill([
        ['type' => 'features', 'data' => ['variant' => 'grid', 'appearance' => ['tone' => 'accent']]],
    ], defaultScheme(DefaultTheme::WARM_CRAFT));

    // WarmCraft says list + muted/airy; the model said grid + accent. Grid and
    // accent survive, and only the unset spacing takes the preset's airy.
    expect($blocks[0]['data']['variant'])->toBe('grid')
        ->and($blocks[0]['data']['appearance'])->toBe(['tone' => 'accent', 'spacing' => 'airy']);
});

it('fill: replaces an invalid variant with the scheme default', function (): void {
    $blocks = stampSchemeDefaults()->fill([
        ['type' => 'features', 'data' => ['variant' => 'no-such-layout']],
    ], defaultScheme(DefaultTheme::WARM_CRAFT));

    expect($blocks[0]['data']['variant'])->toBe('alternating');
});

it('fill: alternates a repeating base band instead of stacking it', function (): void {
    // professional-minimal deliberately says base for every content type — the
    // per-type map cannot alternate, but the position-aware walk can.
    $blocks = stampSchemeDefaults()->fill([
        ['type' => 'features', 'data' => []],
        ['type' => 'offerings', 'data' => []],
        ['type' => 'gallery', 'data' => []],
    ], defaultScheme(DefaultTheme::PROFESSIONAL_MINIMAL));

    expect($blocks[0]['data']['appearance'])->toBe(['tone' => 'base'])
        // The preset's axis opinions ride along with the flipped tone: this is
        // where professional-minimal keeps its old priced-list offerings look.
        ->and($blocks[1]['data']['appearance'])->toBe(['tone' => 'muted', 'width' => 'narrow', 'align' => 'start', 'columns' => 'one', 'item_style' => 'plain'])
        ->and($blocks[2]['data']['appearance'])->toBe(['tone' => 'base']);
});

it('fill: demotes a dark band that would sit directly under another dark band', function (): void {
    // bold-editorial puts gallery AND testimonials on inverted; adjacent, the
    // second becomes muted — never two dark bands next to each other.
    $blocks = stampSchemeDefaults()->fill([
        ['type' => 'gallery', 'data' => []],
        ['type' => 'testimonials', 'data' => []],
    ], defaultScheme(DefaultTheme::BOLD_EDITORIAL));

    expect($blocks[0]['data']['appearance'])->toBe(['tone' => 'inverted', 'spacing' => 'tight', 'width' => 'full'])
        ->and($blocks[1]['data']['appearance'])->toBe(['tone' => 'muted']);
});

it('fill: a model tone resets the alternation state', function (): void {
    $blocks = stampSchemeDefaults()->fill([
        ['type' => 'features', 'data' => ['appearance' => ['tone' => 'inverted']]],
        ['type' => 'contact', 'data' => []],
    ], defaultScheme(DefaultTheme::WARM_CRAFT));

    // Contact's preset muted does not repeat anything — the previous band was
    // the model's inverted — so it lands unflipped. The model's tone survives
    // on features while the preset still fills its unset spacing.
    expect($blocks[0]['data']['appearance'])->toBe(['tone' => 'inverted', 'spacing' => 'airy'])
        ->and($blocks[1]['data']['appearance'])->toBe(['tone' => 'muted', 'spacing' => 'airy']);
});

it('fill: writes nothing over silence, and a spacing-only scheme entry stays spacing-only', function (): void {
    // team has no WarmCraft entry and no flip is needed first in the list,
    // so it keeps its view's own default; prose has a spacing-only entry.
    $blocks = stampSchemeDefaults()->fill([
        ['type' => 'team', 'data' => []],
        ['type' => 'prose', 'data' => []],
    ], defaultScheme(DefaultTheme::WARM_CRAFT));

    expect($blocks[0]['data'])->not->toHaveKey('appearance')
        // team counted as base, so prose (also no tone opinion) flips to
        // muted to keep two silent base bands from stacking — plus its preset
        // airy.
        ->and($blocks[1]['data']['appearance'])->toBe(['tone' => 'muted', 'spacing' => 'airy']);
});

it('fill: skips hero and heading appearance, and leaves unknown types alone', function (): void {
    $blocks = stampSchemeDefaults()->fill([
        ['type' => 'hero', 'data' => []],
        ['type' => 'heading', 'data' => ['content' => 'Divider']],
        ['type' => 'not-a-block', 'data' => ['keep' => 'me']],
        ['type' => 'features', 'data' => []],
    ], defaultScheme(DefaultTheme::WARM_CRAFT));

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

it('fill: a valid model axis choice survives the scheme opinion', function (): void {
    // professional-minimal's offerings entry says columns=one/plain; the model
    // chose two-column cards, and the model wins on every axis it spoke to.
    $blocks = stampSchemeDefaults()->fill([
        ['type' => 'offerings', 'data' => ['appearance' => ['columns' => 'two', 'item_style' => 'card']]],
    ], defaultScheme(DefaultTheme::PROFESSIONAL_MINIMAL));

    $appearance = $blocks[0]['data']['appearance'];

    expect($appearance['columns'])->toBe('two')
        ->and($appearance['item_style'])->toBe('card')
        // The axes the model was silent on still take the preset's opinion.
        ->and($appearance['width'])->toBe('narrow')
        ->and($appearance['align'])->toBe('start');
});
