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
 *
 * {@see variablesFor()} compiles an ARBITRARY token set (the business only
 * supplies brand colors) — the plumbing that lets the page editor preview
 * unsaved design drafts without persisting them.
 */
final class ThemeVariables
{
    /**
     * @return array<string, string>
     */
    public static function variables(Business $business): array
    {
        return self::variablesFor($business->design_tokens, $business);
    }

    /**
     * @return array<string, string>
     */
    public static function variablesFor(DesignTokens $tokens, Business $business): array
    {
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
        return self::styleFor($business->design_tokens, $business);
    }

    public static function styleFor(DesignTokens $tokens, Business $business, string $attribute = 'data-site-theme'): HtmlString
    {
        $declarations = [];

        foreach (self::variablesFor($tokens, $business) as $name => $value) {
            $declarations[] = $name.': '.$value.';';
        }

        return new HtmlString(
            '<style '.$attribute.'>:root{'.implode(' ', $declarations).'}</style>',
        );
    }
}
