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
            ...$tokens->motion->variables(),
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

    /**
     * `$selector` exists for the editor's canvas, which layers an unsaved draft
     * over the saved theme. Both are `:root` blocks, so which one wins comes
     * down to document order — and the draft LOSES that: it is written by the
     * preview view, while the saved theme is emitted by the layout's HEAD_END
     * hook, which runs later. Doubling the selector (`:root:root`) settles it on
     * specificity instead, which no amount of reordering can quietly undo.
     */
    public static function styleFor(DesignTokens $tokens, ?Business $business = null, string $attribute = 'data-site-theme', string $selector = ':root'): HtmlString
    {
        return new HtmlString(
            '<style '.$attribute.'>'.$selector.'{'.self::inline($tokens, $business).'}</style>',
        );
    }

    /**
     * The same declarations as a `style` ATTRIBUTE body, for an element rather
     * than a document.
     *
     * The design surfaces render a live specimen per option — a real button at
     * a candidate corner radius, the palette's actual colours, two lines set in
     * the font pair being considered — by handing each specimen element the
     * variables of a hypothetical token set ({@see DesignTokens::withToken()}).
     * A `<style>` block cannot do that: it would need a generated selector per
     * option, in a stylesheet, for values that only exist for one render.
     *
     * Deliberately not escaped here. Every value comes from a token enum's own
     * `variables()` or from {@see ColorPalette::colors()}, whose one
     * user-authored path (the Brand palette's hexes) is validated by
     * {@see ColorPalette::validHex()} before it is ever stored. Blade escapes
     * the attribute at the call site regardless.
     */
    public static function inline(DesignTokens $tokens, ?Business $business = null): string
    {
        $declarations = [];

        foreach (self::variablesFor($tokens, $business) as $name => $value) {
            $declarations[] = $name.': '.$value.';';
        }

        return implode(' ', $declarations);
    }
}
