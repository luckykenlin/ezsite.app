<?php

declare(strict_types=1);

namespace App\Design;

use Illuminate\Support\Str;

/**
 * The human-readable option maps for every design token, in the
 * `value => label` shape Filament selects and radios consume.
 *
 * Lives in the design module rather than on either editing surface: the
 * Design settings page and the page editor's Design modal both need the same
 * lists, and a UI class owning them would make one page depend on the other.
 */
final class TokenOptions
{
    /**
     * @return array<string, string>
     */
    public static function presets(): array
    {
        return self::map(StylePreset::cases(), static fn (StylePreset $preset): string => $preset->label());
    }

    /**
     * The one-line "what this preset feels like" copy shown under each radio.
     *
     * @return array<string, string>
     */
    public static function presetDescriptions(): array
    {
        return self::map(StylePreset::cases(), static fn (StylePreset $preset): string => $preset->description());
    }

    /**
     * @return array<string, string>
     */
    public static function palettes(): array
    {
        return self::map(ColorPalette::cases(), static fn (ColorPalette $palette): string => self::headline($palette->value));
    }

    /**
     * Font pairs read as the actual families, so the label doubles as a
     * preview of what the site will use.
     *
     * @return array<string, string>
     */
    public static function fontPairs(): array
    {
        return self::map(
            FontPair::cases(),
            static fn (FontPair $pair): string => $pair->headingFamily().' + '.$pair->bodyFamily(),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function radiusScales(): array
    {
        return self::map(RadiusScale::cases(), static fn (RadiusScale $radius): string => self::headline($radius->value));
    }

    /**
     * @return array<string, string>
     */
    public static function densities(): array
    {
        return self::map(SpacingDensity::cases(), static fn (SpacingDensity $density): string => self::headline($density->value));
    }

    /**
     * @template TCase of \BackedEnum
     *
     * @param  list<TCase>  $cases
     * @param  callable(TCase): string  $label
     * @return array<string, string>
     */
    private static function map(array $cases, callable $label): array
    {
        $options = [];

        foreach ($cases as $case) {
            $options[(string) $case->value] = $label($case);
        }

        return $options;
    }

    private static function headline(string $value): string
    {
        return Str::headline($value);
    }
}
