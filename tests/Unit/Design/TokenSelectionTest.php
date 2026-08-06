<?php

declare(strict_types=1);

use App\Design\StylePreset;
use App\Design\TokenKey;
use App\Design\TokenSelection;

/*
 * Both readings are driven off TokenKey::cases() rather than a copy of the four
 * keys, so a fifth token has to satisfy all of this on the day it is added —
 * the same reason the enum exists.
 */
it('normalises a selection to the preset and every token key', function (): void {
    $normalised = TokenSelection::normalise(StylePreset::WarmCraft->tokens()->toArray());

    expect(array_keys((array) $normalised))->toBe(['preset', ...TokenKey::values()])
        ->and($normalised['preset'])->toBe('warm-craft');
});

it('keeps every known key even when the selection is empty', function (): void {
    $normalised = TokenSelection::normalise([]);

    expect(array_keys((array) $normalised))->toBe(['preset', ...TokenKey::values()])
        ->and(array_filter((array) $normalised))->toBeEmpty();
});

/*
 * A key the design surfaces do not know would ride along into the editor's
 * Livewire state and from there into a write; a non-string value would reach a
 * token resolver that expects one. Both are dropped here rather than downstream.
 */
it('drops unknown keys and non-string values', function (): void {
    $normalised = TokenSelection::normalise([
        'preset' => 42,
        'palette' => 'ocean',
        'radius' => ['nested'],
        'shadow' => 'heavy',
    ]);

    expect($normalised)->not->toHaveKey('shadow')
        ->and($normalised['preset'])->toBeNull()
        ->and($normalised['palette'])->toBe('ocean')
        ->and($normalised['radius'])->toBeNull();
});

/*
 * The brand keys ride only as VALID hexes: this runs on data from outside the
 * process and the values end up inside a <style> tag, so the validity check is
 * the injection guard. Present only when valid — an absent key means "keep the
 * saved brand colour", never null.
 */
it('passes brand hexes through only when they are hexes', function (): void {
    $normalised = TokenSelection::normalise([
        'palette' => 'brand',
        'brand_primary' => '#1A2B3C',
        'brand_secondary' => 'not-a-colour',
        'brand_accent' => ['#123456'],
    ]);

    expect($normalised['brand_primary'])->toBe('#1a2b3c')
        ->and($normalised)->not->toHaveKey('brand_secondary')
        ->and($normalised)->not->toHaveKey('brand_accent');
});

it('emits no brand keys for a selection without them', function (): void {
    expect(TokenSelection::normalise(['palette' => 'ocean']))
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

/*
 * The absences are the point: an omitted key means "leave this token alone", so
 * a hidden form field or an unset tool argument must not be written as null.
 */
it('reads changes as only the token keys actually supplied', function (): void {
    $changes = TokenSelection::changes([
        'preset' => 'warm-craft',
        'palette' => 'ocean',
        'radius' => null,
        'density' => 12,
    ]);

    expect($changes)->toBe(['palette' => 'ocean']);
});

it('reads no changes from a selection that supplied none', function (): void {
    expect(TokenSelection::changes([]))->toBeEmpty();
});
