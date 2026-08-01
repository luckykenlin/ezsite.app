<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Pages\PopulateDraftImages;
use App\Models\Business;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;

/**
 * Queued follow-up to {@see GenerateSiteDraftJob}: attach stock photography
 * to the draft that just landed. A separate job, deliberately — the draft
 * job already budgets two 150s AI calls against its 360s timeout, and the
 * photo work is pure HTTP that must never cost the operator a landed draft.
 * The "Site draft ready" notification therefore stays immediate; photos
 * arrive seconds later.
 *
 * One attempt, and no failure notification: every layer below degrades to an
 * empty result rather than throwing, and a draft without photos is exactly
 * the pre-pipeline product, not an error the operator must act on. A timeout
 * mid-walk is harmless — blocks keep their un-consumed `_image_query` keys
 * and the walk is idempotent, so a manual re-dispatch resumes where it died.
 */
#[Timeout(120)]
#[Tries(1)]
final class PopulateDraftImagesJob extends TenantAware
{
    protected function handleInTenant(): void
    {
        $business = Business::query()->first();

        if ($business === null) {
            return;
        }

        resolve(PopulateDraftImages::class)->handle($business);
    }
}
