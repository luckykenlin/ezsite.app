<?php

declare(strict_types=1);

namespace App\Providers;

use Awcodes\Curator\Http\Controllers\MediaController;
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
use Stancl\Tenancy\Events\DeletingTenant;
use Stancl\Tenancy\Events\TenancyEnded;
use Stancl\Tenancy\Events\TenancyInitialized;
use Stancl\Tenancy\Events\TenantCreated;
use Stancl\Tenancy\Jobs\CreateStorageSymlinks;
use Stancl\Tenancy\Jobs\DeleteDomains;
use Stancl\Tenancy\Jobs\RemoveStorageSymlinks;
use Stancl\Tenancy\Listeners\BootstrapTenancy;
use Stancl\Tenancy\Listeners\CreateTenantStorage;
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
 * NOTE FOR UPGRADES: this started as the package's published stub, but the event
 * map has been trimmed to the events this app actually listens to. The stub
 * additionally lists ~35 events mapped to empty arrays as documentation of what
 * is available; {@see bootEvents()} iterates the inner listener array, so an
 * empty entry registers nothing and carrying them was purely cosmetic. If you
 * are diffing against a newer upstream stub, expect those absences — and check
 * the upstream list for newly added events worth handling rather than assuming
 * this map is exhaustive.
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
            // No database provisioning jobs here — this app uses
            // single-database tenancy via Postgres RLS. But the tenant DOES
            // get its own filesystem: FilesystemTenancyBootstrapper points
            // the public disk at storage/{suffix}{tenant}/app/public, which
            // is only servable once public/public-{tenant} links to it.
            // Creating both here means uploads work in a fresh local
            // environment with no manual `tenants:link` step.
            TenantCreated::class => [
                CreateTenantStorage::class,

                JobPipeline::make([
                    CreateStorageSymlinks::class,
                ])->send(fn (TenantCreated $event): Tenant => $event->tenant)->shouldBeQueued(false),
            ],
            DeletingTenant::class => [
                JobPipeline::make([
                    DeleteDomains::class,
                    // Drops the dangling public/public-{tenant} link. The
                    // tenant's FILES are deliberately left on disk
                    // (Jobs\DeleteTenantStorage) — destroying uploaded media
                    // is a product decision, not a side effect of deletion.
                    RemoveStorageSymlinks::class,
                ])->send(fn (DeletingTenant $event): Tenant => $event->tenant)->shouldBeQueued(false),
            ],

            // Tenancy lifecycle
            TenancyInitialized::class => [
                BootstrapTenancy::class,
            ],
            TenancyEnded::class => [
                RevertToCentralContext::class,
            ],

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
        ];
    }

    public function boot(): void
    {
        $this->bootEvents();
        $this->mapRoutes();
        $this->syncRlsPoliciesAfterMigrations();

        $this->makeTenancyMiddlewareHighestPriority();
        $this->tenantizeCuratorRoute();

        // Livewire's update route must be universal: it is one URL serving both
        // the central and every tenant domain, so it identifies the tenant from
        // the request rather than being registered per-domain.
        Livewire::setUpdateRoute(fn (array $handle) => RouteFacade::post('/livewire/update', $handle)
            ->middleware([
                'web',
                'universal',
                InitializeTenancyByDomainOrSubdomain::class,
            ]));

        // Same reasoning for temporary-upload previews, whose controller reads
        // from the tenant-suffixed disk.
        FilePreviewController::$middleware = [
            'web',
            'universal',
            InitializeTenancyByDomainOrSubdomain::class,
        ];
    }

    /**
     * Curator registers its Glide media route with NO middleware. On this
     * RLS setup that is doubly wrong: the metadata lookup would run on the
     * unscoped central connection (cross-tenant media exposure), and the
     * tenant-suffixed public disk root would not resolve, so the file could
     * not be read at all. Package routes register before app providers boot,
     * so the route is patched in place here instead of re-registered.
     * Deliberately no 'web' group: images need no session/cookies.
     */
    private function tenantizeCuratorRoute(): void
    {
        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            if (mb_ltrim($route->getActionName(), '\\') === MediaController::class.'@show') {
                $route->middleware([
                    InitializeTenancyByDomainOrSubdomain::class,
                    PreventAccessFromUnwantedDomains::class,
                ]);
            }
        }
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
