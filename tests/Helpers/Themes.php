<?php

declare(strict_types=1);

use App\Design\ColorPalette;
use App\Design\DesignTokens;
use App\Design\FontPair;
use App\Design\RadiusScale;
use App\Design\SpacingDensity;
use App\Themes\Definitions\DefaultTheme;
use App\Themes\Scheme;
use App\Themes\Theme;
use App\Themes\ThemeDefinition;

/*
 * The default theme's schemes, by slug — and a test theme that adds the
 * brand-palette scheme the default theme lacks.
 *
 * In tests/Helpers rather than at the top of one test file (Pest boots this
 * directory for every run): the scheme used to be an enum case any test could
 * name, and some thirty files reach for one — spelling the theme lookup out in
 * each would be thirty places to change when the lookup does.
 */

/**
 * Throws rather than returning null, so a test that names a slug the default
 * theme does not offer fails at the lookup instead of three assertions later
 * on a null property read.
 */
function defaultScheme(string $slug): Scheme
{
    return Theme::Default->definition()->scheme($slug)
        ?? throw new InvalidArgumentException(sprintf('The default theme has no "%s" scheme.', $slug));
}

/**
 * The default theme plus one scheme on the Brand palette, `house-colours`.
 * The default theme has none, and the brand-hex rules (SetSiteStyle's refusal,
 * a brand tweak riding on a staged scheme) are about exactly that palette.
 */
function brandSchemeTheme(): ThemeDefinition
{
    $default = Theme::Default->definition();

    return new ThemeDefinition(
        label: 'Brand-aware',
        blocks: $default->blocks,
        stylesheet: $default->stylesheet,
        schemes: [
            DefaultTheme::WARM_CRAFT => defaultScheme(DefaultTheme::WARM_CRAFT),
            'house-colours' => new Scheme(
                slug: 'house-colours',
                label: 'House colours',
                description: 'Your own colours.',
                tokens: new DesignTokens('house-colours', ColorPalette::Brand, FontPair::NeoGrotesque, RadiusScale::Md, SpacingDensity::Normal),
                blockVariantDefaults: [],
                blockAppearanceDefaults: [],
            ),
        ],
    );
}
