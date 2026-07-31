<?php

declare(strict_types=1);

namespace App\Actions;

use App\Design\StylePreset;
use App\Design\TokenKey;
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
     * @param  array<array-key, mixed>  $selection  raw form state: preset + every {@see TokenKey}
     */
    public function handle(Business $business, array $selection): Business
    {
        $preset = $this->preset($selection);

        if ($preset instanceof StylePreset && $this->matchesPreset($preset, $selection)) {
            return $this->applyStylePreset->handle($business, $preset);
        }

        return $this->updateDesignTokens->handle($business, $this->tokenChanges($selection));
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

    /**
     * Every token key, keeping only the ones the form actually supplied — an
     * absent (e.g. hidden) field must not be written as null.
     *
     * @param  array<array-key, mixed>  $selection
     * @return array<string, string>
     */
    private function tokenChanges(array $selection): array
    {
        $changes = [];

        foreach (TokenKey::values() as $key) {
            $value = $selection[$key] ?? null;

            if (is_string($value)) {
                $changes[$key] = $value;
            }
        }

        return $changes;
    }
}
