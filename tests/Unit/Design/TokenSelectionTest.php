<?php

declare(strict_types=1);

use App\Design\TokenSelection;
use App\Themes\Definitions\DefaultTheme;

/*
 * A selection is a scheme slug plus brand hexes, never axes: the eight axes on
 * DesignTokens ride in from a scheme's toArray() and are dropped here, so a
 * staged selection has nowhere to carry a single changed axis.
 */
it('normalises a selection to the scheme alone, dropping the axes', function (): void {
    $normalised = TokenSelection::normalise(defaultScheme(DefaultTheme::WARM_CRAFT)->tokens->toArray());

    expect($normalised)->toBe(['scheme' => 'warm-craft']);
});

it('keeps the scheme key even when the selection is empty', function (): void {
    expect(TokenSelection::normalise([]))->toBe(['scheme' => null]);
});

/*
 * A key the design surfaces do not know would ride along into the editor's
 * Livewire state and from there into a write; a non-string value would reach a
 * theme lookup that expects one. Both are dropped here rather than downstream.
 */
it('drops unknown keys and non-string values', function (): void {
    $normalised = TokenSelection::normalise([
        'scheme' => 42,
        'palette' => 'ocean',
        'shadow' => 'heavy',
    ]);

    expect($normalised)->toBe(['scheme' => null]);
});

/*
 * The brand keys ride only as VALID hexes: this runs on data from outside the
 * process and the values end up inside a <style> tag, so the validity check is
 * the injection guard. Present only when valid — an absent key means "keep the
 * saved brand colour", never null.
 */
it('passes brand hexes through only when they are hexes', function (): void {
    $normalised = TokenSelection::normalise([
        'scheme' => 'warm-craft',
        'brand_primary' => '#1A2B3C',
        'brand_secondary' => 'not-a-colour',
        'brand_accent' => ['#123456'],
    ]);

    expect($normalised)->toBe(['scheme' => 'warm-craft', 'brand_primary' => '#1a2b3c']);
});

it('emits no brand keys for a selection without them', function (): void {
    expect(TokenSelection::normalise(['scheme' => 'warm-craft']))
        ->not->toHaveKey('brand_primary')
        ->not->toHaveKey('brand_secondary')
        ->not->toHaveKey('brand_accent');
});

/*
 * Null is the "no style was staged" signal every caller reads. An array in
 * always means an array out, so a normalised selection is never mistaken for an
 * absent one.
 */
it('answers null only for input that is not an array', function (mixed $input): void {
    expect(TokenSelection::normalise($input))->toBeNull();
})->with([null, 'warm-craft', 7, true]);
