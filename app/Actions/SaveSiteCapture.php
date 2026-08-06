<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\SiteSetting;

/**
 * Persists the tenant's site-wide capture surfaces — the offer popup and the
 * sticky mobile call bar — into `site_settings.capture`.
 *
 * Sibling of {@see SaveSiteChrome}, and it writes the same row: a tenant has
 * one settings record, and both editors have to create it if the tenant has
 * never saved either. `updateOrCreate` on `tenant_id` is what keeps them from
 * racing into two rows. Must run in tenant context.
 */
final readonly class SaveSiteCapture
{
    /**
     * @param  array<string, mixed>  $popup
     * @param  array<string, mixed>  $callBar
     */
    public function handle(array $popup, array $callBar): SiteSetting
    {
        return SiteSetting::query()->updateOrCreate(
            ['tenant_id' => tenant('id')],
            ['capture' => ['popup' => $popup, 'call_bar' => $callBar]],
        );
    }
}
