<?php

declare(strict_types=1);

namespace App\Actions;

use App\Design\StylePreset;
use App\Models\Business;

/**
 * Applies a style preset by writing its token bundle onto the business —
 * nothing else. Part of the AI tool surface: the draft generator calls this
 * with the preset the model chose.
 */
final readonly class ApplyStylePreset
{
    public function handle(Business $business, StylePreset $preset): Business
    {
        $business->update(['design_tokens' => $preset->tokens()]);

        return $business;
    }
}
