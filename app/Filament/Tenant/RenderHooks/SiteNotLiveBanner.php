<?php

declare(strict_types=1);

namespace App\Filament\Tenant\RenderHooks;

use App\Filament\Tenant\Resources\PageResource;
use App\Site\OnboardingProgress;
use Filament\Facades\Filament;

/**
 * The panel-wide bar that tells an owner their site does not answer yet.
 *
 * A class rather than a closure in the panel provider, so the guard below is
 * directly unit-testable instead of reachable only through a full panel
 * render.
 */
final class SiteNotLiveBanner
{
    public static function render(): string
    {
        if (! self::shouldWarn()) {
            return '';
        }

        return view('filament.tenant.site-not-live-banner', [
            'pagesUrl' => PageResource::getUrl('index'),
        ])->render();
    }

    /**
     * Three things have to hold before the banner is drawn.
     *
     * The PANEL check is the one that is easy to get wrong: `renderHook()` on a
     * panel is not scoped to that panel — the panel forwards it to the global
     * FilamentView registry when it boots, so once a tenant request has booted
     * this panel the hook stays registered for every later request in the same
     * process. Under php-fpm that process dies at the end of the request, but
     * under Octane (and in the test suite, which reuses one application) a
     * central-panel page would render it, and `PageResource::getUrl()` would then
     * look for a route on the wrong panel and throw.
     *
     * The AUTH check matters because this hook also fires on the panel's own
     * login and password-reset pages, where the viewer may be anybody at all and
     * must be told nothing about the state of someone else's site.
     */
    private static function shouldWarn(): bool
    {
        return Filament::getCurrentOrDefaultPanel()?->getId() === 'tenant'
            && Filament::auth()->check()
            && ! resolve(OnboardingProgress::class)->isLive();
    }
}
