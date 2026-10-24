<?php

declare(strict_types=1);

namespace App\Design;

/**
 * The one reading of a raw design selection — a `scheme` slug plus any brand
 * hexes — arriving from the Site Styles panel, an AI tool call or a cache
 * entry.
 *
 * A selection is NOT a token set. The eight axes on {@see DesignTokens} are
 * how a look is represented; what an operator or the assistant selects is a
 * theme scheme (which supplies all eight at once) and, when that scheme is
 * built on the brand palette, the three brand colours. So the only keys that
 * survive here are the ones a design surface can act on, and the staged
 * shape has nowhere to carry an axis — the reader resolves the slug through
 * the tenant's current theme (`ThemeDefinition::tokensFor()`).
 *
 * {@see normalise()} was written three times before this class (both cache
 * actions and the editor's canvas preview), each a copy that could drift from
 * the others. Static and stateless, and in the design module because the
 * readers are an action, a tool and a Livewire component, so owning this
 * anywhere else would make one of them depend on another.
 */
final class TokenSelection
{
    /**
     * The three brand-colour keys that may ride WITH a selection. They are not
     * tokens — their values are hexes bound for the `businesses.brand_*`
     * columns, not enum members — and they only show on a scheme whose palette
     * is {@see ColorPalette::Brand}.
     */
    public const array BRAND_KEYS = ['brand_primary', 'brand_secondary', 'brand_accent'];

    /**
     * A selection reduced to the keys the design surfaces understand.
     *
     * Callers hand over data from outside the process — cached turn results,
     * cached undo snapshots, Livewire payloads — which is why the input is
     * `mixed` and every value is re-tested rather than trusted. A junk SCHEME
     * cannot reach CSS (the theme answers null for a slug it does not offer),
     * but an unexpected KEY would ride along into the editor's state, so only
     * known ones survive.
     *
     * Returns null only for input that is not an array at all, which every
     * caller reads as "no style was staged". An array in always means an array
     * out, so a normalised selection is never mistaken for an absent one.
     *
     * @return array<string, string|null>|null
     */
    public static function normalise(mixed $selection): ?array
    {
        if (! is_array($selection)) {
            return null;
        }

        $normalised = ['scheme' => self::string($selection, 'scheme')];

        // Brand hexes survive only when they ARE hexes — this runs on data from
        // outside the process, and these values end up inside a <style> tag, so
        // the validity check is the injection guard. Present only when valid,
        // never as null: an absent key means "keep the saved brand colour".
        foreach (self::BRAND_KEYS as $key) {
            $hex = ColorPalette::validHex(self::string($selection, $key));

            if ($hex !== null) {
                $normalised[$key] = $hex;
            }
        }

        return $normalised;
    }

    /**
     * @param  array<array-key, mixed>  $selection
     */
    private static function string(array $selection, string $key): ?string
    {
        $value = $selection[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
