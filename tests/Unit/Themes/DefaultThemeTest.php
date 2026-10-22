<?php

declare(strict_types=1);

use App\Site\Blocks\BlockVocabulary;
use App\Themes\Theme;

/*
 * Transitional parity guard. The default theme restates what the block classes
 * still declare statically; until those statics are removed, the two must not
 * drift, or the editor would offer one set of layouts and the theme another.
 * Delete this file in the slice that makes the block classes read from the
 * theme — at that point there is only one source and nothing to compare.
 */

test('the default theme mirrors the block classes type for type', function (): void {
    $vocabulary = resolve(BlockVocabulary::class)->all();
    $blocks = Theme::Default->definition()->blocks;

    expect(array_keys($blocks))->toEqualCanonicalizing(array_keys($vocabulary));

    foreach ($vocabulary as $type => $contract) {
        $block = $blocks[$type];

        expect($block->variants)->toBe($contract->variantLabels, "$type variants drifted")
            ->and($block->variantAxes)->toBe($contract->variantAxes, "$type variant axes drifted")
            ->and($block->imagelessVariants)->toBe($contract->imagelessVariants, "$type imageless variants drifted");
    }
});
