<?php

declare(strict_types=1);

namespace App\Actions;

use App\Design\ColorPalette;
use App\Mail\SiteMailIdentity;
use App\Models\Business;
use App\Models\Tenant;

/**
 * Resolves the current tenant's mail identity from its Business profile.
 *
 * Runs INSIDE tenant context — the `businesses` read is RLS-scoped, so this can
 * only ever see the current tenant's row.
 *
 * Every field falls back, because a tenant can legitimately have no Business
 * yet: {@see CreateTenant} provisions only the tenant and its subdomain, and a
 * site built by hand rather than from a template may never fill the profile in.
 * An enquiry to such a site still has to produce an email that reads like it
 * came from somewhere, so the tenant's own name and signup address stand in.
 */
final readonly class BuildSiteMailIdentity
{
    public function handle(): SiteMailIdentity
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        $business = Business::query()->first();

        // `->` rather than `?->` on the left of every `??`: the null-coalescing
        // operator has isset() semantics, so it already absorbs a null $business
        // without reading the property at all. PHPStan flags the nullsafe form
        // here as redundant.
        return new SiteMailIdentity(
            siteName: $business->name ?? $tenant->name,
            accent: $business->brand_primary ?? ColorPalette::DEFAULT_BRAND,
            replyToEmail: $business->contact_email ?? $tenant->email,
            phone: $business?->contact_phone,
        );
    }
}
