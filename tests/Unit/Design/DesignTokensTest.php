<?php

declare(strict_types=1);

use App\Design\ColorPalette;
use App\Design\DesignTokens;
use App\Design\FontPair;
use App\Design\RadiusScale;
use App\Design\SpacingDensity;
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

/*
 * The one line the chat assistant reads to know where the site stands. Scheme
 * first (or `custom` for a look no scheme vouches for), then every axis in
 * toArray() order, so the prompt and the tool reply can never describe the same
 * tokens differently.
 */
it('describes itself as the scheme and every axis in order', function (): void {
    expect(defaultScheme(DefaultTheme::CALM_COASTAL)->tokens->describe())
        ->toBe('calm-coastal — ocean, quiet-serif, refined, lg, spacious, curve, sheen, still')
        ->and(DesignTokens::default()->describe())
        ->toBe('custom — default, neo-grotesque, classic, md, normal, none, flat, still');
});
