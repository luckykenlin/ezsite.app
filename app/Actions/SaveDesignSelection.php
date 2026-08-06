<?php

declare(strict_types=1);

namespace App\Actions;

use App\Design\ColorPalette;
use App\Design\StylePreset;
use App\Design\TokenKey;
use App\Design\TokenSelection;
use App\Models\Business;

/**
 * Persists a design form's raw selection, deciding between the two write
 * paths: a preset whose token bundle still matches every fine-tune field is
 * saved AS that preset, anything else is saved as a custom combination
 * (which detaches the preset marker — see UpdateDesignTokens).
 *
 * That "what still counts as a preset" rule is product semantics, and both
 * design surfaces — the Design settings page and the page editor's Design
 * modal — must answer it identically, so it lives here rather than in either
 * page. Input is deliberately the raw Filament form state: callers hand over
 * what the user picked, not a pre-interpreted decision.
 */
final readonly class SaveDesignSelection
{
    public function __construct(
        private ApplyStylePreset $applyStylePreset,
        private UpdateDesignTokens $updateDesignTokens,
    ) {
        //
    }

    /**
     * @param  array<array-key, mixed>  $selection  raw form state: preset + every {@see TokenKey},
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

        $preset = $this->preset($selection);

        if ($preset instanceof StylePreset && $this->matchesPreset($preset, $selection)) {
            return $this->applyStylePreset->handle($business, $preset);
        }

        return $this->updateDesignTokens->handle($business, TokenSelection::changes($selection));
    }

    /**
     * @param  array<array-key, mixed>  $selection
     */
    private function preset(array $selection): ?StylePreset
    {
        $value = $selection['preset'] ?? null;

        return is_string($value) ? StylePreset::tryFrom($value) : null;
    }

    /**
     * @param  array<array-key, mixed>  $selection
     */
    private function matchesPreset(StylePreset $preset, array $selection): bool
    {
        $tokens = $preset->tokens();

        return array_all(
            TokenKey::cases(),
            fn (TokenKey $key): bool => ($selection[$key->value] ?? null) === $key->valueOn($tokens),
        );
    }
}
