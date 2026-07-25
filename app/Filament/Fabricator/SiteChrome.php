<?php

declare(strict_types=1);

namespace App\Filament\Fabricator;

use App\Models\Business;
use App\Models\SiteSetting;

/**
 * The tenant's site-wide header/footer block entries, rendered by the main
 * layout around every page body.
 *
 * Saved configuration (site_settings.header/footer, Fabricator block-entry
 * shape) wins; otherwise a default header/footer renders as soon as the
 * tenant has a Business. A tenant with neither renders no chrome at all —
 * silently, because logging here would emit warnings on every page view of a
 * not-yet-onboarded tenant. Request-scoped for the same reason as
 * {@see BindResolver}: one settings query per render.
 */
final class SiteChrome
{
    private ?SiteSetting $settings = null;

    private bool $settingsLoaded = false;

    public function __construct(private readonly BindResolver $bindResolver)
    {
        //
    }

    /**
     * @return array<int, mixed>
     */
    public function headerBlocks(): array
    {
        return $this->blocks('header');
    }

    /**
     * @return array<int, mixed>
     */
    public function footerBlocks(): array
    {
        return $this->blocks('footer');
    }

    /**
     * @return array<int, mixed>
     */
    private function blocks(string $slot): array
    {
        $settings = $this->settings();
        $stored = $slot === 'header' ? $settings?->header : $settings?->footer;

        if ($stored !== null && $stored !== []) {
            return array_values($stored);
        }

        if (! $this->bindResolver->business() instanceof Business) {
            return [];
        }

        return [['type' => $slot, 'data' => []]];
    }

    private function settings(): ?SiteSetting
    {
        if (! $this->settingsLoaded) {
            $this->settings = SiteSetting::query()->first();
            $this->settingsLoaded = true;
        }

        return $this->settings;
    }
}
