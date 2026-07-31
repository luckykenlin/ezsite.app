<?php

declare(strict_types=1);

use App\Actions\Pages\StampVariantDefaults;
use App\Design\StylePreset;
use App\Site\Blocks\BlockType;
use App\Site\Blocks\BlockVocabulary;

function stampVariantDefaults(): StampVariantDefaults
{
    return resolve(StampVariantDefaults::class);
}

it('gives every type the layout the preset was designed with', function (StylePreset $preset): void {
    $vocabulary = resolve(BlockVocabulary::class);

    foreach ($preset->blockVariantDefaults() as $type => $expected) {
        expect(stampVariantDefaults()->variantFor($type, $preset))->toBe($expected)
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
        'novel' => new BlockType('novel', ['first', 'second'], null, null, [], []),
    ]);

    expect(new StampVariantDefaults($vocabulary)->variantFor('novel', StylePreset::WarmCraft))->toBe('first');
});

it('has no layout to give a type with a single fixed view', function (): void {
    expect(stampVariantDefaults()->variantFor('heading', StylePreset::WarmCraft))->toBeNull();
});

it('has no layout to give a type it does not know', function (): void {
    // Unknown types genuinely reach a real page — it is why the render loop is
    // defensive — so a restyle must not be the thing that throws on one.
    expect(stampVariantDefaults()->variantFor('not-a-block', StylePreset::WarmCraft))->toBeNull();
});

it('restamps a whole list, leaving content and unknown types alone', function (): void {
    $expected = StylePreset::CalmCoastal->blockVariantDefaults();

    $blocks = stampVariantDefaults()->handle([
        ['type' => 'hero', 'data' => ['heading' => 'Keep me']],
        ['type' => 'heading', 'data' => ['content' => 'No variants here']],
        ['type' => 'not-a-block', 'data' => []],
    ], StylePreset::CalmCoastal);

    expect($blocks[0]['data']['variant'])->toBe($expected['hero'])
        ->and($blocks[0]['data']['heading'])->toBe('Keep me')
        ->and($blocks[1]['data'])->not->toHaveKey('variant')
        ->and($blocks[2]['data'])->not->toHaveKey('variant');
});
