<?php

declare(strict_types=1);

namespace App\Site;

use App\Models\SiteSetting;

/**
 * Reads the tenant's single `site_settings` row, once per request.
 *
 * Extracted when a second consumer appeared: {@see SiteChrome} wants the
 * header/footer columns and {@see SiteCapture} wants the capture column, both
 * on every public page render, and each memoizing privately meant two
 * identical queries per page. The "one settings query per render" invariant is
 * pinned by *"it resolves the chrome for both slots from a single settings
 * query"* in tests/Feature/Filament/Fabricator/SiteChromeRenderTest.php.
 *
 * Scoped, like its two consumers: a queue worker serving two tenants must not
 * hand the second one the first one's settings.
 */
final class SiteSettingsLoader
{
    private ?SiteSetting $settings = null;

    private bool $loaded = false;

    /**
     * Never queries outside tenancy, where RLS would not scope the read.
     */
    public function get(): ?SiteSetting
    {
        if ($this->loaded) {
            return $this->settings;
        }

        $this->loaded = true;
        $this->settings = tenant() === null ? null : SiteSetting::query()->first();

        return $this->settings;
    }
}
