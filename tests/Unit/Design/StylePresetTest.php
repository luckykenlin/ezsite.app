<?php

declare(strict_types=1);

use App\Design\StylePreset;
use App\Site\Blocks\BlockVocabulary;

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
 * silent: `StampVariantDefaults::variantFor()` falls back to the first declared
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
