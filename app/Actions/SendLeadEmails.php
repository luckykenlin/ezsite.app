<?php

declare(strict_types=1);

namespace App\Actions;

use App\Mail\EnquiryReceipt;
use App\Mail\NewEnquiry;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the two emails a captured enquiry produces: one to every operator of
 * the site, and a receipt to the enquirer.
 *
 * Runs INSIDE tenant context (the operator lookup is fine either way, but
 * {@see BuildSiteMailIdentity} reads an RLS-scoped table), and off the request —
 * see {@see \App\Jobs\SendLeadEmailsJob} for why an SMTP round trip must not sit
 * inside a public form POST.
 *
 * `$inboxUrl` is a parameter rather than something this action builds, because
 * building it means importing a Filament resource and `App\Actions` must not
 * depend on the admin panel (pinned by tests/Arch/LayeringTest). The listener
 * that starts this chain resolves it while a request still knows the host.
 */
final readonly class SendLeadEmails
{
    public function __construct(private BuildSiteMailIdentity $identities) {}

    public function handle(Lead $lead, string $inboxUrl): void
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        $identity = $this->identities->handle();

        // One message per operator, addressed to them alone: a shared To header
        // would disclose their colleagues' addresses to each other.
        foreach (User::query()->memberOf($tenant->id)->get() as $operator) {
            Mail::to($operator->email, $operator->name)->send(new NewEnquiry($lead, $identity, $inboxUrl));
        }

        // The phone-first surfaces (the sticky mobile call bar) capture leads
        // with no address, and those simply get no receipt.
        if (filled($lead->email)) {
            Mail::to($lead->email, $lead->name)->send(new EnquiryReceipt($lead, $identity));
        }
    }
}
