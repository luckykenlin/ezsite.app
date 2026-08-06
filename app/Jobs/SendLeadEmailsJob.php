<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\SendLeadEmails;
use App\Models\Lead;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;

/**
 * Delivers a captured enquiry's emails off the request.
 *
 * Queued, unlike its sibling {@see \App\Listeners\NotifyOperatorsOfLead}, for two
 * reasons the panel notification does not have: an SMTP handshake takes hundreds
 * of milliseconds inside a public form POST that a visitor is waiting on, and a
 * mail server that is briefly unreachable would turn a perfectly stored lead
 * into a 500 on the visitor's screen. Three attempts with a widening backoff
 * ride out a transient provider failure instead.
 *
 * The lead is re-queried here rather than serialized into the payload: model
 * deserialization happens BEFORE `handle()`, so it would run outside the tenant
 * context this job establishes, on a connection with no RLS session variable
 * set — the same reason {@see PopulateDraftImagesJob} queries inside.
 */
#[Timeout(60)]
#[Tries(3)]
#[Backoff(60, 300)]
final class SendLeadEmailsJob extends TenantAware
{
    public function __construct(
        string $tenantId,
        public readonly int $leadId,
        public readonly string $inboxUrl,
    ) {
        parent::__construct($tenantId);
    }

    protected function handleInTenant(): void
    {
        $lead = Lead::query()->find($this->leadId);

        // Deleted between capture and delivery — rare, but a retry after a
        // provider outage can arrive minutes late, and there is nothing to
        // report about an enquiry that no longer exists.
        if ($lead === null) {
            return;
        }

        resolve(SendLeadEmails::class)->handle($lead, $this->inboxUrl);
    }
}
