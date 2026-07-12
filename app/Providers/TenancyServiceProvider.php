<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\ServiceProvider;
use Livewire\Features\SupportFileUploads\FilePreviewController;
use Livewire\Livewire;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Contracts\Tenant;
use Stancl\Tenancy\Events\BootstrappingTenancy;
use Stancl\Tenancy\Events\CreatingDomain;
use Stancl\Tenancy\Events\CreatingPendingTenant;
use Stancl\Tenancy\Events\CreatingStorageSymlink;
use Stancl\Tenancy\Events\CreatingTenant;
use Stancl\Tenancy\Events\DatabaseCreated;
use Stancl\Tenancy\Events\DatabaseDeleted;
use Stancl\Tenancy\Events\DatabaseMigrated;
use Stancl\Tenancy\Events\DatabaseRolledBack;
use Stancl\Tenancy\Events\DatabaseSeeded;
use Stancl\Tenancy\Events\DeletingDomain;
use Stancl\Tenancy\Events\DeletingTenant;
use Stancl\Tenancy\Events\DomainCreated;
use Stancl\Tenancy\Events\DomainDeleted;
use Stancl\Tenancy\Events\DomainSaved;
use Stancl\Tenancy\Events\DomainUpdated;
use Stancl\Tenancy\Events\EndingTenancy;
use Stancl\Tenancy\Events\InitializingTenancy;
use Stancl\Tenancy\Events\PendingTenantCreated;
use Stancl\Tenancy\Events\PendingTenantPulled;
use Stancl\Tenancy\Events\PullingPendingTenant;
use Stancl\Tenancy\Events\RemovingStorageSymlink;
use Stancl\Tenancy\Events\RevertedToCentralContext;
use Stancl\Tenancy\Events\RevertingToCentralContext;
use Stancl\Tenancy\Events\SavingDomain;
use Stancl\Tenancy\Events\SavingTenant;
use Stancl\Tenancy\Events\StorageSymlinkCreated;
use Stancl\Tenancy\Events\StorageSymlinkRemoved;
use Stancl\Tenancy\Events\TenancyBootstrapped;
use Stancl\Tenancy\Events\TenancyEnded;
use Stancl\Tenancy\Events\TenancyInitialized;
use Stancl\Tenancy\Events\TenantCreated;
use Stancl\Tenancy\Events\TenantDeleted;
use Stancl\Tenancy\Events\TenantMaintenanceModeDisabled;
use Stancl\Tenancy\Events\TenantMaintenanceModeEnabled;
use Stancl\Tenancy\Events\TenantSaved;
use Stancl\Tenancy\Events\TenantUpdated;
use Stancl\Tenancy\Events\UpdatingDomain;
use Stancl\Tenancy\Events\UpdatingTenant;
use Stancl\Tenancy\Jobs\DeleteDomains;
use Stancl\Tenancy\Listeners\BootstrapTenancy;
use Stancl\Tenancy\Listeners\RevertToCentralContext;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromUnwantedDomains;
use Stancl\Tenancy\ResourceSyncing\Events\CentralResourceAttachedToTenant;
use Stancl\Tenancy\ResourceSyncing\Events\CentralResourceDetachedFromTenant;
use Stancl\Tenancy\ResourceSyncing\Events\SyncedResourceDeleted;
use Stancl\Tenancy\ResourceSyncing\Events\SyncedResourceSaved;
use Stancl\Tenancy\ResourceSyncing\Events\SyncMasterDeleted;
use Stancl\Tenancy\ResourceSyncing\Events\SyncMasterRestored;
use Stancl\Tenancy\ResourceSyncing\Listeners\CreateTenantResource;
use Stancl\Tenancy\ResourceSyncing\Listeners\DeleteResourceInTenant;
use Stancl\Tenancy\ResourceSyncing\Listeners\DeleteResourceMapping;
use Stancl\Tenancy\ResourceSyncing\Listeners\DeleteResourcesInTenants;
use Stancl\Tenancy\ResourceSyncing\Listeners\RestoreResourcesInTenants;
use Stancl\Tenancy\ResourceSyncing\Listeners\UpdateOrCreateSyncedResource;

/**
 * Tenancy for Laravel.
 *
 * Documentation: https://tenancyforlaravel.com
 *
 * We can sustainably develop Tenancy for Laravel thanks to our sponsors.
 * Big thanks to everyone listed here: https://github.com/sponsors/stancl
 *
 * You can also support us, and save time, by purchasing these products:
 *   Exclusive content for sponsors: https://sponsors.tenancyforlaravel.com
 *   Multi-Tenant SaaS boilerplate: https://portal.archte.ch/boilerplate
 *   Multi-Tenant Laravel in Production e-book: https://portal.archte.ch/book
 *
 * All of these products can also be accessed at https://portal.archte.ch
 */
final class TenancyServiceProvider extends ServiceProvider
{
    // By default, no namespace is used to support the callable array syntax.
    public static string $controllerNamespace = '';

    /**
     * @return array<class-string, array<int, class-string|JobPipeline>>
     */
    public function events(): array
    {
        return [
            // Tenant events
            CreatingTenant::class => [],
            // No database provisioning jobs here: this app uses single-database tenancy via Postgres RLS,
            // so tenants don't get their own database.
            TenantCreated::class => [],
            SavingTenant::class => [],
            TenantSaved::class => [],
            UpdatingTenant::class => [],
            TenantUpdated::class => [],
            DeletingTenant::class => [
                JobPipeline::make([
                    DeleteDomains::class,
                    // Jobs\DeleteTenantStorage::class,
                    // Jobs\RemoveStorageSymlinks::class,
                ])->send(fn (DeletingTenant $event): Tenant => $event->tenant)->shouldBeQueued(false),
            ],
            TenantDeleted::class => [
                // ResourceSyncing\Listeners\DeleteAllTenantMappings::class,
            ],

            TenantMaintenanceModeEnabled::class => [],
            TenantMaintenanceModeDisabled::class => [],

            // Pending tenant events
            CreatingPendingTenant::class => [],
            PendingTenantCreated::class => [],
            PullingPendingTenant::class => [],
            PendingTenantPulled::class => [],

            // Domain events
            CreatingDomain::class => [],
            DomainCreated::class => [],
            SavingDomain::class => [],
            DomainSaved::class => [],
            UpdatingDomain::class => [],
            DomainUpdated::class => [],
            DeletingDomain::class => [],
            DomainDeleted::class => [],

            // Database events
            DatabaseCreated::class => [],
            DatabaseMigrated::class => [],
            DatabaseSeeded::class => [],
            DatabaseRolledBack::class => [],
            DatabaseDeleted::class => [],

            // Tenancy events
            InitializingTenancy::class => [],
            TenancyInitialized::class => [
                BootstrapTenancy::class,
            ],

            EndingTenancy::class => [],
            TenancyEnded::class => [
                RevertToCentralContext::class,
            ],

            BootstrappingTenancy::class => [],
            TenancyBootstrapped::class => [],
            RevertingToCentralContext::class => [],
            RevertedToCentralContext::class => [],

            // Resource syncing
            SyncedResourceSaved::class => [
                UpdateOrCreateSyncedResource::class,
            ],
            SyncedResourceDeleted::class => [
                DeleteResourceMapping::class,
            ],
            SyncMasterDeleted::class => [
                DeleteResourcesInTenants::class,
            ],
            SyncMasterRestored::class => [
                RestoreResourcesInTenants::class,
            ],
            CentralResourceAttachedToTenant::class => [
                CreateTenantResource::class,
            ],
            CentralResourceDetachedFromTenant::class => [
                DeleteResourceInTenant::class,
            ],

            // Storage symlinks
            CreatingStorageSymlink::class => [],
            StorageSymlinkCreated::class => [],
            RemovingStorageSymlink::class => [],
            StorageSymlinkRemoved::class => [],
        ];
    }

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->bootEvents();
        $this->mapRoutes();
        $this->syncRlsPoliciesAfterMigrations();

        $this->makeTenancyMiddlewareHighestPriority();
        $this->overrideUrlInTenantContext();

        // // Include soft deleted resources in synced resource queries.
        // ResourceSyncing\Listeners\UpdateOrCreateSyncedResource::$scopeGetModelQuery = function (Builder $query) {
        //     if ($query->hasMacro('withTrashed')) {
        //         $query->withTrashed();
        //     }
        // };

        // // To make Livewire v3 work with Tenancy, make the update route universal.
        Livewire::setUpdateRoute(fn (array $handle) => RouteFacade::post('/livewire/update', $handle)
            ->middleware([
                'web',
                'universal',
                InitializeTenancyByDomainOrSubdomain::class,
            ]));

        FilePreviewController::$middleware = [
            'web',
            'universal',
            InitializeTenancyByDomainOrSubdomain::class,
        ];
    }

    /**
     * Set \Stancl\Tenancy\Bootstrappers\RootUrlBootstrapper::$rootUrlOverride here
     * to override the root URL used in CLI while in tenant context.
     *
     * @see \Stancl\Tenancy\Bootstrappers\RootUrlBootstrapper
     */
    private function overrideUrlInTenantContext(): void
    {
        // \Stancl\Tenancy\Bootstrappers\RootUrlBootstrapper::$rootUrlOverride = function (Tenant $tenant, string $originalRootUrl) {
        //     $tenantDomain = $tenant instanceof \Stancl\Tenancy\Contracts\SingleDomainTenant
        //         ? $tenant->domain
        //         : $tenant->domains->first()->domain;
        //
        //     if (is_null($tenantDomain)) {
        //         return $originalRootUrl;
        //     }
        //
        //     $scheme = str($originalRootUrl)->before('://');
        //
        //     if (str_contains($tenantDomain, '.')) {
        //         // Domain identification
        //         return $scheme . '://' . $tenantDomain . '/';
        //     } else {
        //         // Subdomain identification
        //         $originalDomain = str($originalRootUrl)->after($scheme . '://')->before('/');
        //         return $scheme . '://' . $tenantDomain . '.' . $originalDomain . '/';
        //     }
        // };
    }

    private function bootEvents(): void
    {
        foreach ($this->events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof JobPipeline) {
                    $listener = $listener->toListener();
                }

                Event::listen($event, $listener);
            }
        }
    }

    /**
     * RLS policies aren't part of migrations (they're generated dynamically from
     * the schema by `tenants:rls`), so without this they'd only get (re)created
     * when someone remembers to run the command by hand. `tenants:rls` is
     * idempotent, so it's safe to run after every forward migration.
     */
    private function syncRlsPoliciesAfterMigrations(): void
    {
        Event::listen(function (MigrationsEnded $event): void {
            if ($event->method !== 'up' || DB::connection()->getDriverName() !== 'pgsql') {
                return;
            }

            Artisan::call('tenants:rls');
        });
    }

    private function mapRoutes(): void
    {
        $this->app->booted(function (): void {
            if (file_exists(base_path('routes/tenant.php'))) {
                RouteFacade::namespace(self::$controllerNamespace)
                    ->middleware('tenant')
                    ->group(base_path('routes/tenant.php'));
            }

            // $this->cloneRoutes();
        });
    }

    private function makeTenancyMiddlewareHighestPriority(): void
    {
        // PreventAccessFromUnwantedDomains has even higher priority than the identification middleware
        $tenancyMiddleware = array_filter(
            array_merge([PreventAccessFromUnwantedDomains::class], Config::array('tenancy.identification.middleware')),
            is_string(...),
        );

        foreach (array_reverse($tenancyMiddleware) as $middleware) {
            $this->app->make(Kernel::class)->prependToMiddlewarePriority($middleware);
        }
    }
}
