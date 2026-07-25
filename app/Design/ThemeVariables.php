<?php

declare(strict_types=1);

namespace App\Design;

use App\Models\Business;
use Illuminate\Support\HtmlString;

/**
 * Compiles a business's design tokens into the runtime CSS-variable
 * overrides the tenant site's <head> emits. Always the full deterministic
 * set — colors, radius, density, fonts — so rendering never depends on
 * which keys happen to be stored. Every value comes from an enum constant
 * or a regex-validated hex; nothing user-controlled reaches the style tag
 * unescaped.
 */
final class ThemeVariables
{
    /**
     * @return array<string, string>
     */
    public static function variables(Business $business): array
    {
        $tokens = $business->design_tokens;

        return [
            ...$tokens->palette->colors($business),
            ...$tokens->radius->variables(),
            ...$tokens->density->variables(),
            '--font-sans' => $tokens->fontPair->bodyStack(),
            '--font-heading' => $tokens->fontPair->headingStack(),
        ];
    }

    public static function style(Business $business): HtmlString
    {
        $declarations = [];

        foreach (self::variables($business) as $name => $value) {
            $declarations[] = $name.': '.$value.';';
        }

        return new HtmlString(
            '<style data-site-theme>:root{'.implode(' ', $declarations).'}</style>',
        );
    }
}
