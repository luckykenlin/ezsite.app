<?php

declare(strict_types=1);

use App\Design\ColorPalette;
use App\Design\DesignTokens;
use App\Design\FontPair;
use App\Design\RadiusScale;
use App\Design\SpacingDensity;
use App\Design\TokenKey;
use App\Design\TypeStyle;
use App\Themes\Definitions\DefaultTheme;

it('defaults to the neutral token set with no preset', function (): void {
    $tokens = DesignTokens::default();

    expect($tokens->scheme)->toBeNull()
        ->and($tokens->palette)->toBe(ColorPalette::Default)
        ->and($tokens->fontPair)->toBe(FontPair::NeoGrotesque)
        ->and($tokens->typeStyle)->toBe(TypeStyle::Classic)
        ->and($tokens->radius)->toBe(RadiusScale::Md)
        ->and($tokens->density)->toBe(SpacingDensity::Normal);
});

it('round-trips through toArray and fromArray', function (): void {
    $tokens = defaultScheme(DefaultTheme::CALM_COASTAL)->tokens;

    expect(DesignTokens::fromArray($tokens->toArray())->toArray())->toBe($tokens->toArray())
        ->and($tokens->toArray())->toBe([
            'scheme' => 'calm-coastal',
            'palette' => 'ocean',
            'font_pair' => 'quiet-serif',
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
        'scheme' => 42,
        'palette' => 'no-such-palette',
        'radius' => 42,
    ]);

    expect($tokens->scheme)->toBeNull()
        ->and($tokens->palette)->toBe(ColorPalette::Default)
        ->and($tokens->fontPair)->toBe(FontPair::NeoGrotesque)
        ->and($tokens->typeStyle)->toBe(TypeStyle::Classic)
        ->and($tokens->radius)->toBe(RadiusScale::Md)
        ->and($tokens->density)->toBe(SpacingDensity::Normal);
});

/*
 * The scheme slug is carried, not validated: this module knows nothing about
 * themes, so whether `retired-look` still names a scheme in the tenant's theme
 * is the reader's question (`ThemeDefinition::scheme()` answers null). Dropping
 * it here would make a theme switch silently forget which look a site was on.
 */
it('keeps a scheme slug it cannot vouch for', function (): void {
    expect(DesignTokens::fromArray(['scheme' => 'retired-look'])->scheme)->toBe('retired-look');
});

it('replaces only the given tokens and detaches the scheme on manual overrides', function (): void {
    $tokens = defaultScheme(DefaultTheme::WARM_CRAFT)->tokens->with(radius: RadiusScale::None, typeStyle: TypeStyle::Impact);

    expect($tokens->scheme)->toBeNull()
        ->and($tokens->radius)->toBe(RadiusScale::None)
        ->and($tokens->typeStyle)->toBe(TypeStyle::Impact)
        ->and($tokens->palette)->toBe(ColorPalette::WarmSand)
        ->and($tokens->fontPair)->toBe(FontPair::WarmEditorial)
        ->and($tokens->density)->toBe(SpacingDensity::Spacious);
});

it('replaces one token addressed by its key, leaving the rest alone', function (): void {
    $tokens = defaultScheme(DefaultTheme::WARM_CRAFT)->tokens->withToken(TokenKey::Radius, RadiusScale::None);

    expect($tokens->scheme)->toBeNull()
        ->and($tokens->radius)->toBe(RadiusScale::None)
        ->and($tokens->palette)->toBe(ColorPalette::WarmSand)
        ->and($tokens->fontPair)->toBe(FontPair::WarmEditorial);
});

it('falls back to the token default when the value does not belong to the key', function (): void {
    // The specimen renderer drives withToken() from a loop over every key and
    // every option, so a mismatched pair should draw the default rather than
    // raise a TypeError through a half-rendered panel.
    $tokens = defaultScheme(DefaultTheme::WARM_CRAFT)->tokens->withToken(TokenKey::Radius, ColorPalette::Ocean);

    expect($tokens->radius)->toBe(RadiusScale::Md);
});
