<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\LeadCaptured;
use App\Filament\Tenant\Resources\Leads\LeadResource;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

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
     * Panel members come from {@see User::memberOf()}, which resolves them
     * through the RLS-exempt `tenant_user` pivot — so this works whatever the
     * current connection is. A tenant with no members yet simply gets no
     * notification; the lead is already safely stored.
     */
    public function handle(LeadCaptured $event): void
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        $users = User::query()->memberOf($tenant->id)->get();

        if ($users->isEmpty()) {
            return;
        }

        $lead = $event->lead;

        Notification::make()
            ->title(sprintf('New enquiry from %s', $lead->displayName()))
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
