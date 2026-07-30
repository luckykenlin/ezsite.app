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

it('only defaults block variants that exist in the vocabulary', function (StylePreset $preset): void {
    $vocabulary = resolve(BlockVocabulary::class)->all();

    foreach ($preset->blockVariantDefaults() as $type => $variant) {
        expect($vocabulary)->toHaveKey($type)
            ->and($vocabulary[$type]->variants)->toContain($variant);
    }
})->with(StylePreset::cases());
