<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\LeadCaptured;
use App\Filament\Tenant\Resources\Leads\LeadResource;
use App\Jobs\SendLeadEmailsJob;
use App\Models\Tenant;

/**
 * Hands a captured enquiry to the mail queue.
 *
 * A second listener beside {@see NotifyOperatorsOfLead} rather than another
 * branch inside it: the two channels have opposite delivery semantics. The panel
 * bell is one insert and must land in the same transaction-adjacent moment as
 * the capture; email is a network call that has to be retryable and must not be
 * able to fail a visitor's form submission.
 *
 * The inbox URL is resolved HERE, not in the job. `LeadResource::getUrl()` is
 * `route()` underneath, so its host comes from the current request — which on
 * this path is the tenant's own domain, exactly what the link needs. On a queue
 * worker there is no request, `route()` would fall back to `app.url` (the
 * central domain), and both panels answer on `/admin`, so the link would point
 * at the wrong panel on the wrong host.
 */
final readonly class QueueLeadEmails
{
    public function handle(LeadCaptured $event): void
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        dispatch(new SendLeadEmailsJob($tenant->id, $event->lead->id, LeadResource::getUrl('index', panel: 'tenant')));
    }
}
