<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Reviews\BuildReviewLink;
use App\Enums\ReviewRequestStatus;
use App\Models\ReviewRequest;
use Illuminate\Http\RedirectResponse;

/**
 * The counter QR and the receipt link: `/r/{token}` on the tenant's own domain,
 * stamped and forwarded to Google's review composer for that branch.
 *
 * On the tenant's domain rather than a central shortener, deliberately. The customer
 * sees the salon's own address before they see Google's, which is the only trust
 * signal available in the half-second between a scan and a redirect — and it means
 * the token lookup is RLS-scoped, so a token printed for one tenant cannot resolve
 * on another's site.
 *
 * `clicked_at` is stamped on the FIRST scan and then left, so the timestamp answers
 * "did anybody ever use this card" rather than "when was the last scan". The status
 * moves once and stays; there is deliberately no `converted` state, because whether
 * a review was actually left can only be read back off Google, which needs the API
 * approval this whole feature exists to not wait for. Inventing that number is how a
 * dashboard stops being believed.
 */
final class ReviewRedirectController extends Controller
{
    public function __invoke(string $token, BuildReviewLink $links): RedirectResponse
    {
        $request = ReviewRequest::query()->where('token', $token)->first();

        abort_if($request === null, 404);

        $destination = $links->destination($request);

        // The place id was cleared after the card was printed. 404 rather than
        // bounce the customer somewhere useless — and the panel will show the link
        // as broken for the same reason.
        abort_if($destination === null, 404);

        if ($request->clicked_at === null) {
            $request->update([
                'clicked_at' => now(),
                'status' => ReviewRequestStatus::Clicked,
            ]);
        }

        // 302, not 301: the destination depends on a mutable place id, and a
        // permanently-cached redirect would outlive a corrected one in every browser
        // that ever followed it.
        return redirect()->away($destination);
    }
}
