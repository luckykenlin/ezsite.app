<?php

declare(strict_types=1);

use App\Site\Blocks\SectionTone;
use App\Themes\Definitions\DefaultTheme;
use App\Themes\Scheme;
use App\Themes\Theme;

/*
 * The design decisions baked into the default theme's eight schemes — the ones
 * a structural sweep over every theme cannot express, because a second theme
 * is free to make them differently. They stay pinned here so the transitional
 * theme keeps looking like the product it wraps.
 */

/*
 * The words an operator actually types. Pinned as literals rather than derived,
 * because the point is that these specific requests land somewhere: "premium"
 * resolving to nothing is the exact bug the synonyms exist to fix.
 */
it('has a home for the feelings operators ask for', function (string $word): void {
    $matches = array_filter(
        Theme::Default->definition()->schemes,
        static fn (Scheme $scheme): bool => in_array($word, $scheme->synonyms, true),
    );

    expect($matches)->toHaveCount(1);
})->with(['premium', 'warm', 'modern', 'bold', 'calm', 'playful', 'minimal', 'clean']);

/*
 * The point of the whole feature: schemes that read as different websites. If
 * every scheme shipped the same appearances, tokens and variants would be doing
 * all the work again and "make it more premium" would keep producing the same
 * flat stack of bands in a different hue.
 */
it('gives the schemes genuinely different section rhythms', function (): void {
    $rhythms = array_map(
        static fn (Scheme $scheme): string => json_encode($scheme->blockAppearanceDefaults, JSON_THROW_ON_ERROR),
        Theme::Default->definition()->schemes,
    );

    expect(array_unique($rhythms))->toHaveSameSize($rhythms);
});

/*
 * The dark tone is the loudest thing a scheme can reach for, so exactly one
 * owns it. Two "dramatic" schemes is how a menu of eight starts feeling like a
 * menu of three.
 */
it('reserves the dark tone for the one scheme whose whole idea it is', function (): void {
    $withInverted = array_keys(array_filter(
        Theme::Default->definition()->schemes,
        static function (Scheme $scheme): bool {
            $tones = array_column($scheme->blockAppearanceDefaults, 'tone');

            return in_array(SectionTone::Inverted->value, $tones, true);
        },
    ));

    expect($withInverted)->toBe([DefaultTheme::BOLD_EDITORIAL]);
});
