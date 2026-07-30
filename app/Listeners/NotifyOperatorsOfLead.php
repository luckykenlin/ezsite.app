<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\LeadCaptured;
use App\Filament\Tenant\Resources\Leads\LeadResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Puts a new enquiry in the panel's notification bell, linked to the inbox.
 *
 * Deliberately NOT queued: it runs inside the tenant context the capture
 * established, and queueing would push it to a worker where `tenant('id')` is
 * unset unless the job carries the tenant itself (see {@see \App\Jobs\TenantAware}).
 * The work is one query plus one insert, so there is nothing to gain.
 */
final readonly class NotifyOperatorsOfLead
{
    /**
     * Panel members are found through the `tenant_user` pivot, which is
     * exempt from RLS on purpose — so this works whatever the current
     * connection is. A tenant with no members yet simply gets no
     * notification; the lead is already safely stored.
     */
    public function handle(LeadCaptured $event): void
    {
        $users = User::query()
            ->whereHas('tenants', fn (Builder $query): Builder => $query->whereKey(tenant('id')))
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        $lead = $event->lead;

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
