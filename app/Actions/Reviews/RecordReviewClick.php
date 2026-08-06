<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Enums\ReviewRequestStatus;
use App\Models\ReviewRequest;

/**
 * Stamp a review link as used.
 *
 * `clicked_at` is stamped on the FIRST scan and then left, so the timestamp
 * answers "did anybody ever use this card" rather than "when was the last
 * scan". The status moves once and stays; there is deliberately no
 * `converted` state, because whether a review was actually left can only be
 * read back off Google, which needs the API approval this whole feature
 * exists to not wait for. Inventing that number is how a dashboard stops
 * being believed.
 */
final readonly class RecordReviewClick
{
    public function handle(ReviewRequest $request): void
    {
        if ($request->clicked_at !== null) {
            return;
        }

        $request->update([
            'clicked_at' => now(),
            'status' => ReviewRequestStatus::Clicked,
        ]);
    }
}
