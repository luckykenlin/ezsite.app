<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Reviews\BuildReviewDestinationUrl;
use App\Actions\Reviews\RecordReviewClick;
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
 */
final class ReviewRedirectController extends Controller
{
    public function __invoke(string $token, BuildReviewDestinationUrl $destinationUrl, RecordReviewClick $recordClick): RedirectResponse
    {
        $request = ReviewRequest::query()->where('token', $token)->first();

        abort_if($request === null, 404);

        $destination = $destinationUrl->handle($request);

        // The place id was cleared after the card was printed. 404 rather than
        // bounce the customer somewhere useless — and the panel will show the link
        // as broken for the same reason.
        abort_if($destination === null, 404);

        $recordClick->handle($request);

        // 302, not 301: the destination depends on a mutable place id, and a
        // permanently-cached redirect would outlive a corrected one in every browser
        // that ever followed it.
        return redirect()->away($destination);
    }
}
