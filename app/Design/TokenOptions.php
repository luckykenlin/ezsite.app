<?php

declare(strict_types=1);

namespace App\Design;

use BackedEnum;
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
     * One token's options. Replaces the four near-identical per-token methods
     * that used to live here, so both design surfaces can render their
     * fine-tune fields by looping {@see TokenKey::cases()} instead of naming
     * each key twice (once for the field, once for the option map).
     *
     * @return array<string, string>
     */
    public static function for(TokenKey $key): array
    {
        $options = [];

        foreach ($key->tokenClass()::cases() as $case) {
            $options[(string) $case->value] = self::optionLabel($case);
        }

        return $options;
    }

    /**
     * One value's label. Font pairs are the exception the rest of the tokens
     * prove: their names ("modern-sans") say less than the families they
     * actually resolve to, so their label doubles as a preview of what the site
     * will use. Everything else reads well as its own headlined key.
     */
    private static function optionLabel(BackedEnum $case): string
    {
        if ($case instanceof FontPair) {
            return $case->headingFamily().' + '.$case->bodyFamily();
        }

        return self::headline((string) $case->value);
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
