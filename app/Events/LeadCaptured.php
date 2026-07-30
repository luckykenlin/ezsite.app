<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Lead;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An enquiry was recorded from a public tenant site.
 *
 * Exists so {@see \App\Actions\CaptureLead} can stay a domain action. Telling the
 * operator means a Filament database notification carrying a panel URL, which is
 * knowledge the admin panel owns — importing `LeadResource` into `App\Actions` to
 * build it made a lead-capture action depend on the panel being installed and
 * put UI copy in the domain layer. The listener that reacts to this lives beside
 * the panel instead ({@see \App\Listeners\NotifyOperatorsOfLead}).
 *
 * Fired INSIDE tenant context, and its listener runs synchronously, so
 * `tenant('id')` is still resolved when the notification is addressed.
 */
final readonly class LeadCaptured
{
    use Dispatchable;

    public function __construct(public Lead $lead)
    {
        //
    }
}
