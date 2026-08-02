<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Tenant\Resources\PageResource;
use App\Models\Tenant;
use App\Site\OnboardingProgress;
use Awcodes\Curator\CuratorPlugin;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromUnwantedDomains;
use Z3d0X\FilamentFabricator\FilamentFabricatorPlugin;

final class TenantPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('tenant')
            ->path('admin')
            ->viteTheme('resources/css/filament/tenant/theme.css')
            ->login()
            // Filament only mails the link to a user its `canAccessPanel()`
            // returns true for, so a member of another tenant asking for a reset
            // on this domain gets the same neutral confirmation and no email —
            // no cross-tenant account probing, and no link that would land on a
            // 403 after being spent.
            ->passwordReset()
            ->profile()
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Tenant/Resources'), for: 'App\Filament\Tenant\Resources')
            ->discoverPages(in: app_path('Filament/Tenant/Pages'), for: 'App\Filament\Tenant\Pages')
            ->discoverClusters(in: app_path('Filament/Tenant/Clusters'), for: 'App\Filament\Tenant\Clusters')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Tenant/Widgets'), for: 'App\Filament\Tenant\Widgets')
            // No FilamentInfoWidget here, unlike the central panel: "Filament
            // v5.x / documentation / GitHub" is developer furniture, and this
            // dashboard belongs to someone who runs a nail salon.
            ->widgets([
                AccountWidget::class,
            ])
            // The site-is-not-live banner, panel-wide.
            ->renderHook(PanelsRenderHook::TOPBAR_AFTER, fn (): string => $this->siteNotLiveBanner())
            ->plugins([
                FilamentFabricatorPlugin::make(),
                CuratorPlugin::make()
                    ->label('Media')
                    ->navigationIcon(Heroicon::OutlinedPhoto),
            ])
            ->navigationItems([
                NavigationItem::make('Visit site')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (): ?string => $this->currentTenantDomainUrl(), shouldOpenInNewTab: true)
                    ->sort(99),
            ])
            ->middleware([
                InitializeTenancyByDomainOrSubdomain::class,
                PreventAccessFromUnwantedDomains::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->databaseNotifications()
            ->spa();
    }

    /**
     * The bar that tells an owner their site does not answer yet, or an empty
     * string when it has nothing to say.
     */
    private function siteNotLiveBanner(): string
    {
        if (! $this->shouldWarnThatSiteIsNotLive()) {
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
    private function shouldWarnThatSiteIsNotLive(): bool
    {
        return Filament::getCurrentOrDefaultPanel()?->getId() === 'tenant'
            && Filament::auth()->check()
            && ! resolve(OnboardingProgress::class)->isLive();
    }

    private function currentTenantDomainUrl(): ?string
    {
        /** @var Tenant|null $currentTenant */
        $currentTenant = tenant();

        return $currentTenant?->domain?->getUrl();
    }
}
