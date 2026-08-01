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

it('gives every type the layout the preset was designed with', function (StylePreset $preset): void {
    $vocabulary = resolve(BlockVocabulary::class);

    foreach ($preset->blockVariantDefaults() as $type => $expected) {
        expect(stampPresetDefaults()->variantFor($type, $preset))->toBe($expected)
            ->and($vocabulary->get($type)->variants)->toContain($expected);
    }
})->with(StylePreset::cases());

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
