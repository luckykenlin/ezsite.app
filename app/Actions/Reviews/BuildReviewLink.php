<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Enums\ReviewChannel;
use App\Enums\ReviewRequestStatus;
use App\Models\Location;
use App\Models\ReviewRequest;
use Illuminate\Support\Str;

/**
 * The tracked "leave us a review" link for one of a tenant's branches.
 *
 * Idempotent per (location, channel): one durable link per counter card, not a
 * fresh row per page load. The alternative — a token per ask — is what a SENDING
 * layer needs, and there isn't one; a QR taped to a till is handed out a thousand
 * times and has to keep working.
 *
 * Requires the branch's Google place id, and refuses rather than guessing. A
 * "review us" button pointing at a search results page is worse than no button:
 * the customer has to find the business themselves, and most give up.
 *
 * Where the link SENDS a customer is {@see BuildReviewDestinationUrl}'s job —
 * the redirect route needs that half without any of this create machinery.
 *
 * COMPLIANCE, from docs/reviews-module.md's red lines and not negotiable: this asks
 * real customers for honest reviews. There is no filtering step and there must
 * never be one — review gating (asking only the happy ones) is banned outright by
 * Yelp and incentivised reviews are banned by Google. The link is the same link for
 * everybody who gets handed it.
 */
final readonly class BuildReviewLink
{
    /**
     * The durable link for a branch, or null when it has no place id yet.
     */
    public function handle(Location $location, ReviewChannel $channel = ReviewChannel::Link): ?ReviewRequest
    {
        if (blank($location->google_place_id)) {
            return null;
        }

        $existing = ReviewRequest::query()
            ->where('location_id', $location->id)
            ->where('channel', $channel)
            ->first();

        if ($existing instanceof ReviewRequest) {
            return $existing;
        }

        return ReviewRequest::query()->create([
            'tenant_id' => tenant('id'),
            'business_id' => $location->business_id,
            'location_id' => $location->id,
            'channel' => $channel,
            // Written explicitly rather than left to the column default, so the
            // row's starting state is visible here next to the transition that
            // RecordReviewClick owns.
            'status' => ReviewRequestStatus::Queued,
            // 12 of Str::random's alphabet is ~71 bits — far past guessing, and short
            // enough to print on a receipt. Not the place id itself: that would leak
            // which profile a link points at and make every tenant's link forgeable.
            'token' => Str::random(12),
        ]);
    }
}
