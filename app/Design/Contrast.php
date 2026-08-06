<?php

declare(strict_types=1);

namespace App\Design;

/**
 * Picks a readable foreground (near-black or white) for an arbitrary brand
 * color by WCAG relative-luminance contrast — the simplest correct way to
 * derive `*-content` colors for the Brand palette without a color library.
 */
final class Contrast
{
    private const string DARK = 'oklch(21% 0.006 285.885)';

    private const string LIGHT = 'oklch(98% 0 0)';

    /**
     * @param  string  $hex  a validated `#rrggbb` color
     */
    public static function contentFor(string $hex): string
    {
        $luminance = self::relativeLuminance($hex);

        $contrastWithLight = 1.05 / ($luminance + 0.05);
        $contrastWithDark = ($luminance + 0.05) / 0.05;

        return $contrastWithLight >= $contrastWithDark ? self::LIGHT : self::DARK;
    }

    private static function relativeLuminance(string $hex): float
    {
        $channels = sscanf($hex, '#%02x%02x%02x');

        $linear = array_map(function (mixed $channel): float {
            $scaled = (int) $channel / 255;

            return $scaled <= 0.03928 ? $scaled / 12.92 : (($scaled + 0.055) / 1.055) ** 2.4;
        }, is_array($channels) ? $channels : [0, 0, 0]);

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }
}
