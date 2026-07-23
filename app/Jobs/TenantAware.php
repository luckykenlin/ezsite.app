<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\RunInTenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Base for jobs that write tenant-scoped data. Carries the tenant key in the
 * payload and re-establishes the RLS context on the worker via RunInTenant,
 * regardless of whether it was dispatched from tenant or central context.
 *
 * central-dispatched jobs (cron, webhooks) have no ambient tenant context, so
 * they cannot rely on QueueTenancyBootstrapper; this base makes the context
 * explicit and idempotent (re-initializing the same tenant is safe).
 *
 * @see RunInTenant
 */
abstract class TenantAware implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $tenantId) {}

    abstract protected function handleInTenant(): void;

    final public function handle(): void
    {
        resolve(RunInTenant::class)->handle($this->tenantId, fn () => $this->handleInTenant());
    }
}
