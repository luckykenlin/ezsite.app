<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Models\ReviewRequest;

/**
 * Where a tracked review link sends the customer: Google's review composer
 * for that branch.
 *
 * Split from {@see BuildReviewLink} because the two run on different paths
 * with different needs — this one is pure URL building, resolved on every
 * `/r/{token}` hit, and needs none of the find-or-create machinery the panel
 * page uses to mint links.
 */
final readonly class BuildReviewDestinationUrl
{
    /**
     * Google's own write-a-review entry point. Not an API — a public URL that opens
     * the review composer against a place, which is why this needs no approval.
     */
    private const string GOOGLE_WRITE_REVIEW = 'https://search.google.com/local/writereview?placeid=%s';

    public function handle(ReviewRequest $request): ?string
    {
        $placeId = $request->location?->google_place_id;

        return blank($placeId) ? null : sprintf(self::GOOGLE_WRITE_REVIEW, urlencode((string) $placeId));
    }
}
