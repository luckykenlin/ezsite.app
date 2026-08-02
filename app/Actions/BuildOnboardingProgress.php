<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PageStatus;
use App\Models\Business;
use App\Models\Location;
use App\Models\Page;
use App\Models\Tenant;
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
    public function __construct(private SiteCapture $capture) {}

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

        $business = Business::query()->first();
        $location = Location::query()->where('is_primary', true)->first();

        return new OnboardingProgress([
            OnboardingTask::PublishSite->value => Page::query()
                ->where('status', PageStatus::Published)
                ->exists(),

            // Provisioning nulls this deliberately — the template's street
            // address belonged to its invented business — so a blank one is
            // outstanding work rather than a missing feature.
            OnboardingTask::SiteAddress->value => filled($location?->address_line1),

            OnboardingTask::PhoneNumber->value => $this->hasOwnPhone($tenant, $business?->contact_phone),

            OnboardingTask::Logo->value => filled($business?->logo_path),

            OnboardingTask::CaptureSurface->value => $this->capture->popupEnabled()
                || $this->capture->callBarEnabled(),
        ]);
    }

    /**
     * Whether the number on the site is the owner's own.
     *
     * The sharp case: `ProvisionSiteFromTemplate` falls back to the template
     * demo profile's phone when the wizard's optional phone field is skipped, so
     * a real business can go live advertising an invented one's number. Nothing
     * else in the product would ever tell them.
     *
     * A site with no template recorded (hand-built, or drafted by the assistant)
     * has no example number to inherit, so any number it has is its own.
     */
    private function hasOwnPhone(Tenant $tenant, ?string $phone): bool
    {
        if ($phone === null) {
            return false;
        }

        $template = $tenant->template;

        if ($template === null) {
            return true;
        }

        return $phone !== $template->definition()->demoProfile->phone;
    }
}
