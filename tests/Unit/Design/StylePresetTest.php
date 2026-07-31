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
