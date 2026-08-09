<?php

declare(strict_types=1);

namespace App\Design;

use BackedEnum;
use Illuminate\Support\Str;

/**
 * The human-readable label for every value a design token can take, as a
 * `value => label` map.
 *
 * Lives in the design module rather than on a consumer: the Site Styles panel
 * labels its specimens from this, and {@see \App\Ai\Tools\SetSiteStyle} builds
 * the assistant's enum from the same keys — so a UI class owning it would put
 * the AI tool behind a Filament page.
 *
 * A `presets()` counterpart used to sit beside this, for a `Radio` of preset
 * NAMES. The panel draws each preset as a specimen of itself now and reads
 * {@see StylePreset::label()} directly, so the map went with the radio.
 */
final class TokenOptions
{
    /**
     * One token's options. Replaces the four near-identical per-token methods
     * that used to live here, so a surface can render every token by looping
     * {@see TokenKey::cases()} instead of naming each key twice (once for the
     * control, once for the option map).
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

    private static function headline(string $value): string
    {
        return Str::headline($value);
    }
}
