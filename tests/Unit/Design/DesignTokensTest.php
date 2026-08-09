<?php

declare(strict_types=1);

use App\Design\ColorPalette;
use App\Design\DesignTokens;
use App\Design\FontPair;
use App\Design\RadiusScale;
use App\Design\SpacingDensity;
use App\Design\StylePreset;
use App\Design\TokenKey;
use App\Design\TypeStyle;

it('defaults to the neutral token set with no preset', function (): void {
    $tokens = DesignTokens::default();

    expect($tokens->preset)->toBeNull()
        ->and($tokens->palette)->toBe(ColorPalette::Default)
        ->and($tokens->fontPair)->toBe(FontPair::ModernSans)
        ->and($tokens->typeStyle)->toBe(TypeStyle::Classic)
        ->and($tokens->radius)->toBe(RadiusScale::Md)
        ->and($tokens->density)->toBe(SpacingDensity::Normal);
});

it('round-trips through toArray and fromArray', function (): void {
    $tokens = StylePreset::CalmCoastal->tokens();

    expect(DesignTokens::fromArray($tokens->toArray())->toArray())->toBe($tokens->toArray())
        ->and($tokens->toArray())->toBe([
            'preset' => 'calm-coastal',
            'palette' => 'ocean',
            'font_pair' => 'modern-sans',
            'type_style' => 'refined',
            'radius' => 'lg',
            'density' => 'spacious',
            'divider' => 'curve',
            'accent' => 'sheen',
            'motion' => 'still',
        ]);
});

it('silently falls back to defaults for unknown or missing stored values', function (): void {
    $tokens = DesignTokens::fromArray([
        'preset' => 'deleted-preset',
        'palette' => 'no-such-palette',
        'radius' => 42,
    ]);

    expect($tokens->preset)->toBeNull()
        ->and($tokens->palette)->toBe(ColorPalette::Default)
        ->and($tokens->fontPair)->toBe(FontPair::ModernSans)
        ->and($tokens->typeStyle)->toBe(TypeStyle::Classic)
        ->and($tokens->radius)->toBe(RadiusScale::Md)
        ->and($tokens->density)->toBe(SpacingDensity::Normal);
});

it('replaces only the given tokens and detaches the preset on manual overrides', function (): void {
    $tokens = StylePreset::WarmCraft->tokens()->with(radius: RadiusScale::None, typeStyle: TypeStyle::Impact);

    expect($tokens->preset)->toBeNull()
        ->and($tokens->radius)->toBe(RadiusScale::None)
        ->and($tokens->typeStyle)->toBe(TypeStyle::Impact)
        ->and($tokens->palette)->toBe(ColorPalette::WarmSand)
        ->and($tokens->fontPair)->toBe(FontPair::ElegantSerif)
        ->and($tokens->density)->toBe(SpacingDensity::Spacious);
});

it('replaces one token addressed by its key, leaving the rest alone', function (): void {
    $tokens = StylePreset::WarmCraft->tokens()->withToken(TokenKey::Radius, RadiusScale::None);

    expect($tokens->preset)->toBeNull()
        ->and($tokens->radius)->toBe(RadiusScale::None)
        ->and($tokens->palette)->toBe(ColorPalette::WarmSand)
        ->and($tokens->fontPair)->toBe(FontPair::ElegantSerif);
});

it('falls back to the token default when the value does not belong to the key', function (): void {
    // The specimen renderer drives withToken() from a loop over every key and
    // every option, so a mismatched pair should draw the default rather than
    // raise a TypeError through a half-rendered panel.
    $tokens = StylePreset::WarmCraft->tokens()->withToken(TokenKey::Radius, ColorPalette::Ocean);

    expect($tokens->radius)->toBe(RadiusScale::Md);
});
