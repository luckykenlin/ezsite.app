<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Tenant\Navigation\VisitSiteItem;
use App\Filament\Tenant\RenderHooks\SiteNotLiveBanner;
use Awcodes\Curator\CuratorPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
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
            // The site-is-not-live banner, panel-wide. The class owns the
            // panel-id and auth guards — renderHook() is global, not
            // panel-scoped (see its docblock).
            ->renderHook(PanelsRenderHook::TOPBAR_AFTER, SiteNotLiveBanner::render(...))
            ->plugins([
                FilamentFabricatorPlugin::make(),
                CuratorPlugin::make()
                    ->label('Media')
                    ->navigationIcon(Heroicon::OutlinedPhoto),
            ])
            ->navigationItems([
                VisitSiteItem::make(),
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
}
