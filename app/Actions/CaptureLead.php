<?php

declare(strict_types=1);

namespace App\Actions;

use App\Filament\Tenant\Resources\Leads\LeadResource;
use App\Models\Lead;
use App\Models\Location;
use App\Models\Page;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Records an enquiry from the public tenant site and tells the operator about
 * it: a database notification (the panel's bell) to every member of the
 * tenant, carrying a link straight to the inbox.
 *
 * Runs inside tenant context — the Lead write is RLS-scoped and guarded by
 * RequiresTenantContext, so a lead can never land on the wrong tenant.
 */
final readonly class CaptureLead
{
    /**
     * @param  array{name: string, email?: string|null, phone?: string|null, message?: string|null}  $data
     */
    public function handle(array $data, ?Location $location = null, ?Page $page = null, ?string $ipAddress = null): Lead
    {
        $lead = DB::transaction(fn (): Lead => Lead::query()->create([
            'tenant_id' => tenant('id'),
            'location_id' => $location?->getKey(),
            'page_id' => $page?->getKey(),
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'message' => $data['message'] ?? null,
            'ip_address' => $ipAddress,
        ]));

        $this->notifyOperators($lead);

        return $lead;
    }

    /**
     * Panel members are found through the `tenant_user` pivot, which is
     * exempt from RLS on purpose — so this works whatever the current
     * connection is. A tenant with no members yet simply gets no
     * notification; the lead is already safely stored.
     */
    private function notifyOperators(Lead $lead): void
    {
        $users = User::query()
            ->whereHas('tenants', fn (Builder $query): Builder => $query->whereKey(tenant('id')))
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        Notification::make()
            ->title(sprintf('New enquiry from %s', $lead->name))
            ->body($lead->contactLine() ?? $lead->message ?? '')
            ->icon(Heroicon::OutlinedInbox)
            ->success()
            ->actions([
                Action::make('view')
                    ->label('Open inbox')
                    ->url(LeadResource::getUrl('index', panel: 'tenant'))
                    ->markAsRead(),
            ])
            ->sendToDatabase($users);
    }
}
