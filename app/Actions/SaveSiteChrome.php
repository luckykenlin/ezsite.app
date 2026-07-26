<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\SiteSetting;

/**
 * Persists the tenant's site-wide header/footer block entries. An emptied
 * slot stores null, falling back to the default chrome (see SiteChrome).
 * Shared by the SiteChromeSettings page and the page editor's in-canvas
 * chrome editing; must run in tenant context.
 */
final readonly class SaveSiteChrome
{
    /**
     * @param  array<array-key, mixed>|null  $header  fabricator block entries, or null for the default
     * @param  array<array-key, mixed>|null  $footer
     */
    public function handle(?array $header, ?array $footer): SiteSetting
    {
        return SiteSetting::query()->updateOrCreate(
            ['tenant_id' => tenant('id')],
            [
                'header' => is_array($header) && $header !== [] ? array_values($header) : null,
                'footer' => is_array($footer) && $footer !== [] ? array_values($footer) : null,
            ],
        );
    }
}
