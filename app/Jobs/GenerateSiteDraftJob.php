<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\GenerateSiteDraft;
use App\Exceptions\SiteDraftInvalid;
use App\Models\Business;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;

/**
 * Queued wrapper around GenerateSiteDraft: a generation call takes tens of
 * seconds, far too long for a panel request. Extends TenantAware so the RLS
 * write context is re-established on the worker; the agent itself is called
 * synchronously inside the job (laravel/ai's own queue() would run without
 * tenant context). One attempt only — the operator retries from the panel.
 */
// Two generation attempts (one retry on schema drift) at up to 150s each.
#[Timeout(360)]
#[Tries(1)]
final class GenerateSiteDraftJob extends TenantAware
{
    public function __construct(string $tenantId, private readonly int $userId)
    {
        parent::__construct($tenantId);
    }

    protected function handleInTenant(): void
    {
        $user = User::query()->findOrFail($this->userId);

        try {
            $page = resolve(GenerateSiteDraft::class)->handle(Business::query()->firstOrFail());
        } catch (SiteDraftInvalid $siteDraftInvalid) {
            Log::warning('site_draft.rejected', ['tenant_id' => $this->tenantId, 'reason' => $siteDraftInvalid->getMessage()]);

            Notification::make()
                ->title('Site draft failed')
                ->body($siteDraftInvalid->getMessage().' Adjust the business profile and try again.')
                ->danger()
                ->sendToDatabase($user);

            return;
        }

        // Photos are a follow-up, never part of this job's budget: the draft
        // is already landed and notified, and a photo failure must cost
        // nothing but the photos.
        if (config()->boolean('stock-photos.enabled')) {
            dispatch(new PopulateDraftImagesJob($this->tenantId));
        }

        Notification::make()
            ->title('Site draft ready')
            ->body(sprintf('“%s” is waiting for your review in Pages.', $page->title))
            ->success()
            ->sendToDatabase($user);
    }
}
