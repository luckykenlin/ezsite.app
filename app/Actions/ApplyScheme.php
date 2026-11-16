<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Business;
use App\Themes\Scheme;

/**
 * Applies a theme scheme by writing its token bundle onto the business —
 * nothing else. Part of the AI tool surface: the draft generator calls this
 * with the scheme the model chose.
 */
final readonly class ApplyScheme
{
    public function handle(Business $business, Scheme $scheme): Business
    {
        $business->update(['design_tokens' => $scheme->tokens]);

        return $business;
    }
}
