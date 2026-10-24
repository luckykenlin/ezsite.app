<?php

declare(strict_types=1);

namespace App\Actions;

use App\Design\ColorPalette;
use App\Design\TokenSelection;
use App\Models\Business;
use App\Themes\CurrentTheme;
use App\Themes\Scheme;

/**
 * Persists a design surface's raw selection: the brand hexes onto the
 * business row, and the chosen scheme — resolved through the tenant's current
 * theme — as the site's tokens.
 *
 * There is no "custom" write path any more. A site's look is a scheme plus
 * brand colours, so a slug the current theme does not offer (another theme's,
 * a retired one, one the browser invented) is simply not a scheme here and is
 * ignored rather than stored or substituted; the brand hexes in the same
 * selection still land, because they are independent of which scheme is on.
 * No scheme at all leaves the tokens exactly as they were.
 *
 * Both design surfaces — the Design settings page and the page editor's style
 * rail — and the chat's "Apply" button pass their selection through here, so
 * this is the single place that decides what applying a design means. Input is
 * deliberately the raw staged selection: callers hand over what was picked,
 * not a pre-interpreted decision.
 */
final readonly class SaveDesignSelection
{
    public function __construct(
        private ApplyScheme $applyScheme,
        private CurrentTheme $theme,
    ) {
        //
    }

    /**
     * @param  array<array-key, mixed>  $selection  raw staged selection: `scheme`
     *                                              plus any {@see TokenSelection::BRAND_KEYS} hexes
     */
    public function handle(Business $business, array $selection): Business
    {
        // Brand hexes first, so a selection that picks a brand-palette scheme
        // and names its colours in the same save renders from them immediately.
        // Absent keys keep their current values (a hidden field is not an
        // erasure).
        $hexes = [];

        foreach (TokenSelection::BRAND_KEYS as $key) {
            $value = $selection[$key] ?? null;
            $hex = ColorPalette::validHex(is_string($value) ? $value : null);

            if ($hex !== null) {
                $hexes[$key] = $hex;
            }
        }

        if ($hexes !== []) {
            $business->update($hexes);
        }

        $scheme = $this->scheme($selection);

        if ($scheme instanceof Scheme) {
            return $this->applyScheme->handle($business, $scheme);
        }

        return $business;
    }

    /**
     * @param  array<array-key, mixed>  $selection
     */
    private function scheme(array $selection): ?Scheme
    {
        $value = $selection['scheme'] ?? null;

        return is_string($value) ? $this->theme->definition()->scheme($value) : null;
    }
}
