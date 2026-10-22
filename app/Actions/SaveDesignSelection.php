<?php

declare(strict_types=1);

namespace App\Actions;

use App\Design\ColorPalette;
use App\Design\TokenKey;
use App\Design\TokenSelection;
use App\Models\Business;
use App\Themes\CurrentTheme;
use App\Themes\Scheme;

/**
 * Persists a design form's raw selection, deciding between the two write
 * paths: a scheme whose token bundle still matches every fine-tune field is
 * saved AS that scheme, anything else is saved as a custom combination
 * (which detaches the scheme marker — see UpdateDesignTokens).
 *
 * The scheme is resolved through the tenant's current theme, so a slug from
 * another theme (or a retired one) is simply not a scheme here and the
 * selection saves as custom — the same outcome as a hand-tuned look.
 *
 * That "what still counts as a scheme" rule is product semantics, and both
 * design surfaces — the Design settings page and the page editor's Design
 * modal — must answer it identically, so it lives here rather than in either
 * page. Input is deliberately the raw Filament form state: callers hand over
 * what the user picked, not a pre-interpreted decision.
 */
final readonly class SaveDesignSelection
{
    public function __construct(
        private ApplyScheme $applyScheme,
        private UpdateDesignTokens $updateDesignTokens,
        private CurrentTheme $theme,
    ) {
        //
    }

    /**
     * @param  array<array-key, mixed>  $selection  raw form state: scheme + every {@see TokenKey},
     *                                              plus any {@see TokenSelection::BRAND_KEYS} hexes
     */
    public function handle(Business $business, array $selection): Business
    {
        // Brand hexes first, so a selection that switches to the brand palette
        // and names its colours in the same save renders from them immediately.
        // Absent keys keep their current values (a hidden field is not an
        // erasure), and this stays the single design write path — the Design
        // page and the chat rail both pass their hexes through here.
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

        if ($scheme instanceof Scheme && $this->matchesScheme($scheme, $selection)) {
            return $this->applyScheme->handle($business, $scheme);
        }

        return $this->updateDesignTokens->handle($business, TokenSelection::changes($selection));
    }

    /**
     * @param  array<array-key, mixed>  $selection
     */
    private function scheme(array $selection): ?Scheme
    {
        $value = $selection['scheme'] ?? null;

        return is_string($value) ? $this->theme->definition()->scheme($value) : null;
    }

    /**
     * @param  array<array-key, mixed>  $selection
     */
    private function matchesScheme(Scheme $scheme, array $selection): bool
    {
        $tokens = $scheme->tokens;

        return array_all(
            TokenKey::cases(),
            fn (TokenKey $key): bool => ($selection[$key->value] ?? null) === $key->valueOn($tokens),
        );
    }
}
