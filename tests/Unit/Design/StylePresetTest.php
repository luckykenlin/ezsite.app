<?php

declare(strict_types=1);

use App\Design\StylePreset;
use App\Site\Blocks\BlockVocabulary;
use App\Site\Blocks\SectionSpacing;
use App\Site\Blocks\SectionTone;

it('bundles complete, self-referencing tokens for every preset', function (StylePreset $preset): void {
    expect($preset->tokens()->preset)->toBe($preset)
        ->and($preset->vibes())->not->toBeEmpty()
        ->and($preset->label())->not->toBeEmpty()
        ->and($preset->description())->not->toBeEmpty();
})->with(StylePreset::cases());

/*
 * synonyms() is the whole grounding for "make it more premium": vibes() holds
 * INDUSTRY nouns (bakery, law, saas), so without these adjectives the model has
 * nothing to match a feeling against and free-associates among six labels.
 *
 * Disjointness is the property that makes it work. An adjective naming two
 * presets grounds neither, and the failure is invisible — the model picks one,
 * plausibly, and the operator just gets a look they did not ask for.
 */
it('grounds each preset in adjectives that name no other preset', function (): void {
    $seen = [];

    foreach (StylePreset::cases() as $preset) {
        expect($preset->synonyms())->not->toBeEmpty();

        foreach ($preset->synonyms() as $word) {
            expect($seen)->not->toHaveKey($word);

            $seen[$word] = $preset->value;
        }
    }
});

/*
 * The words an operator actually types. Pinned as literals rather than derived,
 * because the point is that these specific requests land somewhere: "premium"
 * resolving to nothing is the exact bug this feature exists to fix.
 */
it('has a home for the feelings operators ask for', function (string $word): void {
    $matches = array_filter(
        StylePreset::cases(),
        static fn (StylePreset $preset): bool => in_array($word, $preset->synonyms(), true),
    );

    expect($matches)->toHaveCount(1);
})->with(['premium', 'warm', 'modern', 'bold', 'calm', 'playful', 'minimal', 'clean']);

it('only defaults block variants that exist in the vocabulary', function (StylePreset $preset): void {
    $vocabulary = resolve(BlockVocabulary::class)->all();

    foreach ($preset->blockVariantDefaults() as $type => $variant) {
        expect($vocabulary)->toHaveKey($type)
            ->and($vocabulary[$type]->variants)->toContain($variant);
    }
})->with(StylePreset::cases());

/*
 * The other direction, and the one that was missing.
 *
 * The assertion above only checks the entries that EXIST are valid, so adding a
 * block type with variants and forgetting to give the six presets a default was
 * silent: `StampPresetDefaults::variantFor()` falls back to the first declared
 * variant, so every preset would quietly lay the new section out identically and
 * "make it more premium" would leave it untouched. Nothing failed, the site just
 * looked slightly wrong in a way nobody could attribute.
 */
it('gives every variant-bearing block type a default', function (StylePreset $preset): void {
    $defaults = $preset->blockVariantDefaults();
    $missing = [];

    foreach (resolve(BlockVocabulary::class)->all() as $type => $contract) {
        if ($contract->variants !== [] && ! array_key_exists($type, $defaults)) {
            $missing[] = $type;
        }
    }

    // Named rather than counted, so the failure says which block to go and fix.
    expect($missing)->toBeEmpty();
})->with(StylePreset::cases());

/*
 * The per-SECTION half of a preset. Its validity rules are deliberately the
 * INVERSE of the variant half's, which is why they get their own tests rather
 * than an extra assertion above: a missing variant silently makes every preset
 * lay a block out identically, so all of them are required — but a missing
 * appearance falls back to the value the block's own view declares, which is a
 * considered default rather than a degradation. Totality here would force six
 * opinions about every future block type, most of which should have none.
 */
it('only defaults appearances for page block types, with values from the scales', function (StylePreset $preset): void {
    $vocabulary = resolve(BlockVocabulary::class);

    foreach ($preset->blockAppearanceDefaults() as $type => $appearance) {
        // Chrome renders outside the section shell, so an entry for it would be
        // a setting with nowhere to land.
        expect($vocabulary->isAddableToPage($type))->toBeTrue()
            ->and(array_keys($appearance))->each->toBeIn(['tone', 'spacing']);

        if (array_key_exists('tone', $appearance)) {
            expect(SectionTone::tryFrom($appearance['tone']))->toBeInstanceOf(SectionTone::class);
        }

        if (array_key_exists('spacing', $appearance)) {
            expect(SectionSpacing::tryFrom($appearance['spacing']))->toBeInstanceOf(SectionSpacing::class);
        }
    }
})->with(StylePreset::cases());

/*
 * Two exemptions worth pinning, because both are decisions rather than
 * omissions and a future preset author would otherwise "fix" them:
 *
 *  - hero: its VARIANT already decides its weight (the preset that wants a
 *    dramatic opening picks full-bleed-overlay, which is dark by construction),
 *    so a tone here fights the variant it was chosen with.
 *  - heading: it is a divider inside the page's flow, not a band of its own.
 *    Give it a background and a section title becomes a section.
 */
it('never gives a hero or a bare heading an appearance', function (StylePreset $preset): void {
    expect($preset->blockAppearanceDefaults())
        ->not->toHaveKey('hero')
        ->not->toHaveKey('heading');
})->with(StylePreset::cases());

/*
 * The point of the whole feature: six presets that read as six websites. If
 * every preset shipped the same appearances, tokens and variants would be doing
 * all the work again and "make it more premium" would keep producing the same
 * flat stack of bands in a different hue.
 */
it('gives the presets genuinely different section rhythms', function (): void {
    $rhythms = array_map(
        static fn (StylePreset $preset): string => json_encode($preset->blockAppearanceDefaults(), JSON_THROW_ON_ERROR),
        StylePreset::cases(),
    );

    expect(array_unique($rhythms))->toHaveSameSize($rhythms);
});

/*
 * The dark tone is the loudest thing a preset can reach for, so exactly one
 * owns it. Two "dramatic" presets is how a menu of six starts feeling like a
 * menu of three.
 */
it('reserves the dark tone for the one preset whose whole idea it is', function (): void {
    $withInverted = array_values(array_filter(
        StylePreset::cases(),
        static fn (StylePreset $preset): bool => in_array(
            SectionTone::Inverted->value,
            array_column($preset->blockAppearanceDefaults(), 'tone'),
            true,
        ),
    ));

    expect($withInverted)->toBe([StylePreset::BoldEditorial]);
});
