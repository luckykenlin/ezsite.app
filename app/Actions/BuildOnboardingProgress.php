<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\Tenant;
use App\Site\BindResolver;
use App\Site\OnboardingProgress;
use App\Site\OnboardingTask;
use App\Site\SiteCapture;
use RuntimeException;

/**
 * Reads the current tenant's real state and reports which onboarding tasks are
 * still outstanding.
 *
 * Runs INSIDE tenant context; every read here is RLS-scoped. Four small queries
 * (three of them single-row), which is why the result is memoized per request
 * through the `OnboardingProgress` scoped binding rather than by caching
 * anything — a cached answer would keep congratulating an owner who has just
 * unpublished their site, or keep nagging one who has just fixed it.
 */
final readonly class BuildOnboardingProgress
{
    public function __construct(
        private SiteCapture $capture,
        private BindResolver $bindResolver,
    ) {
        //
    }

    public function handle(): OnboardingProgress
    {
        /** @var Tenant|null $tenant */
        $tenant = tenant();

        // Fail loud rather than fail open. Reads are not covered by
        // RequiresTenantContext (the central cockpit legitimately reads across
        // tenants on the BYPASSRLS connection), so without this a caller outside
        // tenancy would quietly answer "how far through setup is this owner?"
        // using the first Business row in the installation.
        throw_unless($tenant instanceof Tenant, RuntimeException::class, sprintf(
            '%s reads RLS-scoped tables and can only run inside a tenant context.',
            self::class,
        ));

        // Through BindResolver — the request-scoped reader every render-path
        // consumer already shares — rather than an eighth ad-hoc
        // `Business::query()->first()`.
        $business = $this->bindResolver->business();
        $location = $this->bindResolver->location(null);

        return new OnboardingProgress([
            OnboardingTask::PublishSite->value => Page::query()
                ->where('status', PageStatus::Published)
                ->exists(),

            // Provisioning nulls this deliberately — the template's street
            // address belonged to its invented business — so a blank one is
            // outstanding work rather than a missing feature.
            OnboardingTask::SiteAddress->value => filled($location?->address_line1),

            // Same write-time policy as the address: provisioning stores the
            // owner's number or nothing, so "is it filled" IS the question.
            // (It used to store the template demo's number when the wizard's
            // field was skipped, which forced this row to reverse-engineer
            // whose number it was looking at.)
            OnboardingTask::PhoneNumber->value => filled($business?->contact_phone),

            // Through logoUrl(), not a column: the panel's picker writes
            // `logo_media_id` and `logo_path` is only the legacy fallback, so
            // testing either one alone would leave this task outstanding forever
            // for an owner who has already uploaded a logo. The question the row
            // actually asks is "does the site show a logo", which is exactly
            // what the render side asks.
            OnboardingTask::Logo->value => $business?->logoUrl() !== null,

            OnboardingTask::CaptureSurface->value => $this->capture->popupEnabled()
                || $this->capture->callBarEnabled(),
        ]);
    }
}
