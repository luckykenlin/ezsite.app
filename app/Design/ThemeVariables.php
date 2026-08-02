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
 * unsaved design drafts without persisting them, and what lets the CENTRAL
 * marketing site dogfood this system with a fixed preset and no business at
 * all. Hence the nullable business: a null one simply contributes no brand
 * overrides, and the palette's own colors stand. Deciding whether there IS a
 * business belongs to the caller — the Fabricator render hook bails out on a
 * null tenant; this class does not need to know why.
 */
final class ThemeVariables
{
    /**
     * @return array<string, string>
     */
    public static function variablesFor(DesignTokens $tokens, ?Business $business = null): array
    {
        return [
            ...$tokens->palette->colors($business),
            ...$tokens->radius->variables(),
            ...$tokens->density->variables(),
            ...$tokens->typeStyle->variables(),
            ...$tokens->divider->variables(),
            ...$tokens->accent->variables(),
            '--font-sans' => $tokens->fontPair->bodyStack(),
            '--font-heading' => $tokens->fontPair->headingStack(),
            // Not a custom property, but it belongs in the same :root block:
            // without it a dark palette gets light-scheme form controls and
            // scrollbars, which is the one part of the page CSS cannot paint.
            'color-scheme' => $tokens->palette->isDark() ? 'dark' : 'light',
        ];
    }

    public static function style(Business $business): HtmlString
    {
        return self::styleFor($business->design_tokens, $business);
    }

    public static function styleFor(DesignTokens $tokens, ?Business $business = null, string $attribute = 'data-site-theme'): HtmlString
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
