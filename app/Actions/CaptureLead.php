<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\LeadSource;
use App\Events\LeadCaptured;
use App\Http\Middleware\RememberLeadAttribution;
use App\Models\Lead;
use App\Models\Location;
use App\Models\Page;
use Illuminate\Support\Facades\DB;

/**
 * Records an enquiry from the public tenant site.
 *
 * Runs inside tenant context — the Lead write is RLS-scoped and guarded by
 * RequiresTenantContext, so a lead can never land on the wrong tenant.
 *
 * Telling the operator is a separate concern: this fires {@see LeadCaptured} and
 * {@see \App\Listeners\NotifyOperatorsOfLead} builds the panel notification. That
 * keeps the Filament dependency (and the inbox URL) out of the domain layer, and
 * means capturing a lead does not require the panel to be installed.
 */
final readonly class CaptureLead
{
    /**
     * @param  array{name?: string|null, email?: string|null, phone?: string|null, message?: string|null, reserved_date?: string|null, reserved_time?: string|null, party_size?: int|null}  $data
     * @param  array<string, string|null>  $attribution  first-touch UTM/referrer, keyed by lead
     *                                                   column exactly as {@see RememberLeadAttribution::read()}
     *                                                   returns it — the narrowing lives there,
     *                                                   beside the writer, and a stray key here
     *                                                   fails the insert loudly
     */
    public function handle(
        array $data,
        LeadSource $source = LeadSource::ContactForm,
        ?Location $location = null,
        ?Page $page = null,
        ?string $ipAddress = null,
        array $attribution = [],
    ): Lead {
        $lead = DB::transaction(fn (): Lead => Lead::query()->create([
            'tenant_id' => tenant('id'),
            'location_id' => $location?->getKey(),
            'page_id' => $page?->getKey(),
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'message' => $data['message'] ?? null,
            'reserved_date' => $data['reserved_date'] ?? null,
            'reserved_time' => $data['reserved_time'] ?? null,
            'party_size' => $data['party_size'] ?? null,
            'source' => $source,
            'ip_address' => $ipAddress,
            ...$attribution,
        ]));

        // Dispatched after the transaction commits, so a listener can never
        // observe (or notify about) a lead that then rolled back.
        event(new LeadCaptured($lead));

        return $lead;
    }
}
