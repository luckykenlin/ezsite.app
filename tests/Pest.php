<?php

declare(strict_types=1);

use App\Actions\Library\FindOrImportLibraryPhoto;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Stancl\Tenancy\Events\TenantCreated;
use Tests\Concerns\InteractsWithTenancy;
use Tests\Concerns\MakesStockPhotos;
use Tests\Concerns\VisitsTenantPages;
use Tests\TestCase;

/*
 * The whole suite runs against a real Postgres database with RLS — the same
 * engine as production — so tenant isolation is always exercised for real.
 * Each test gets its own migrate:fresh (one database per parallel token).
 * Non-tenancy tests run as the superuser connection, which bypasses RLS, so
 * they read/write freely; tenancy tests call tenancy()->initialize() to switch
 * to the restricted RLS role and observe isolation.
 */
/**
 * Tenant keys created by the current test, collected so afterEach can remove
 * exactly this test's public symlinks (see the cleanup below).
 *
 * @var list<string>
 */
$createdTenantKeys = [];

/**
 * The database every suite runs against: one Postgres database per parallel
 * token, migrated fresh, with the tenancy bootstrappers pointed at it.
 *
 * Shared by the two bindings below rather than copied into each, because the
 * awkward parts — the per-token database name, creating that database on first
 * use, the per-token filesystem suffix — are exactly the parts that must not
 * drift between them.
 *
 * @param  array<string, mixed>  $overrides  extra config a suite needs applied
 *                                           before the migration runs
 */
$prepareDatabase = function (array $overrides = []) use (&$createdTenantKeys): void {
    $createdTenantKeys = [];

    Event::listen(TenantCreated::class, function (TenantCreated $event) use (&$createdTenantKeys): void {
        $createdTenantKeys[] = (string) $event->tenant->getTenantKey();
    });

    Str::createRandomStringsNormally();
    Str::createUuidsNormally();

    $baseDatabase = 'ezsite_testing';
    $token = ParallelTesting::token();
    $database = $token ? sprintf('%s_test_%s', $baseDatabase, $token) : $baseDatabase;

    config(array_merge([
        'database.default' => 'pgsql',
        'database.connections.pgsql.database' => $database,
        // The package's CentralConnection trait always queries whatever connection
        // this key names, regardless of database.default, so it must point at pgsql too.
        'tenancy.database.central_connection' => 'pgsql',
        // CacheTenancyBootstrapper doesn't support the 'array' store, so use the
        // real 'database' store here instead.
        'cache.default' => 'database',
        'tenancy.cache.stores' => ['database'],
        // FilesystemTenancyBootstrapper names tenant storage dirs
        // "{suffix_base}{tenant_id}" under the shared storage_path(), and the
        // afterEach below globs that base to delete them. Two things must never
        // land in the same glob:
        //
        //   - another parallel runner's live tenant dirs — deleting one mid-run
        //     makes that process's next storage write throw. Hence the process
        //     token in the base.
        //   - the DEVELOPER'S OWN tenant dirs. storage_path() is the real one in
        //     a local serial run, so the production base ('tenant') globbed
        //     `storage/tenant*` and wiped every local tenant's uploaded media.
        //     Hence 'tenant_test' even with no token: a uuid can't follow it.
        //
        // Anything set here must therefore stay distinct from config/tenancy.php's
        // suffix_base; the storage test below guards that.
        'tenancy.filesystem.suffix_base' => $token ? sprintf('tenant_test_token%s_', $token) : 'tenant_test_',
    ], $overrides));

    DB::purge('pgsql');

    // Each parallel test process gets its own Postgres database (suffixed by
    // its process token) so concurrent migrate:fresh calls don't race on shared tables.
    if ($database !== $baseDatabase) {
        try {
            Schema::connection('pgsql')->hasTable('migrations');
        } catch (QueryException) {
            config(['database.connections.pgsql.database' => $baseDatabase]);
            DB::purge('pgsql');

            Schema::connection('pgsql')->createDatabase($database);

            config(['database.connections.pgsql.database' => $database]);
            DB::purge('pgsql');
        }
    }

    Artisan::call('migrate:fresh', [
        '--path' => ['database/migrations'],
        '--force' => true,
    ]);
};

/**
 * Undoes what a test left behind on the filesystem and on the connection pool.
 * Shared for the same reason as the bootstrap above: both entries here are
 * fixes for bugs that only appear under parallel runs, so one copy.
 */
$cleanUpAfterTest = function () use (&$createdTenantKeys): void {
    // End tenancy so a test that leaves it initialized can't leak the RLS
    // session context into the next test. Idempotent when already ended.
    tenancy()->end();

    // Close every DB connection this test opened. Tenancy initialization
    // opens extra connections (tenant, tenant_host_connection) that would
    // otherwise accumulate as idle Postgres sessions across a worker's
    // test sequence — at 8 parallel workers that exhausts the default
    // max_connections=100 and later tests die with "too many clients".
    foreach (array_keys(DB::getConnections()) as $connectionName) {
        DB::purge($connectionName);
    }

    // FilesystemTenancyBootstrapper creates {suffix_base}{tenant_id} directories
    // under storage_path() as a side effect of initializing tenancy; clean them up
    // so they don't pile up. The glob is scoped to THIS process's token-specific
    // suffix_base (set in beforeEach) so a parallel runner never deletes a tenant
    // dir a concurrent process is still using. rescue() covers the residual case
    // where our own dir vanishes between the glob and the delete.
    foreach (File::glob(storage_path(config('tenancy.filesystem.suffix_base').'*')) as $tenantStoragePath) {
        rescue(fn () => File::deleteDirectory($tenantStoragePath), report: false);
    }

    // TenantCreated also links public/public-{tenant} at each tenant's
    // storage dir (so uploads are servable). Those links are now dangling;
    // remove the ones for tenants this test created. Tenant keys are uuids,
    // so a parallel runner's links can never be caught by this filter.
    foreach ($createdTenantKeys as $tenantKey) {
        rescue(fn () => File::delete(public_path('public-'.$tenantKey)), report: false);
    }

    $createdTenantKeys = [];
};

pest()->extend(TestCase::class)
    ->use(InteractsWithTenancy::class, MakesStockPhotos::class)
    ->beforeEach(function () use ($prepareDatabase): void {
        Http::preventStrayRequests();
        Process::preventStrayProcesses();
        Sleep::fake();

        // Filament's layout resolves its theme through Vite, so every panel page
        // render needs public/build/manifest.json — a file that's gitignored and
        // never built in CI. Swapping in Laravel's Vite stub keeps the suite
        // independent of a compiled front-end bundle.
        $this->withoutVite();

        $this->freezeTime();

        // The SHARED photo library, isolated per test.
        //
        // It is the one disk `FilesystemTenancyBootstrapper` deliberately does
        // not suffix, so without this every run wrote real files into
        // `storage/app/library/photos` and left them there — nearly a thousand
        // had accumulated. `LibraryPhotoFactory` names its rows from a faker
        // slug, and faker's uniqueness is per PROCESS, so a slug would
        // eventually land on a file some earlier run had left behind and a
        // test asserting "the catalogue row outlived its file" would find one.
        // A rare, unattributable failure that only ever appeared in a full
        // parallel run.
        //
        // The disk's own `url` is carried across, because `Storage::fake()`
        // otherwise swaps in the generic `/storage` root and the library's
        // public URL — the thing that proves a preview is served from the
        // SHARED disk and not a tenant one — stops saying `/library`.
        Storage::fake(FindOrImportLibraryPhoto::DISK, [
            'url' => config('filesystems.disks.'.FindOrImportLibraryPhoto::DISK.'.url'),
        ]);

        $prepareDatabase();
    })
    ->afterEach($cleanUpAfterTest)
    ->in('Feature', 'Unit');

/*
 * The browser suite drives a real Chromium against the compiled bundle, so four
 * of the things above are actively harmful here and are left out: withoutVite()
 * (these tests exist to run the real assets — `npm run build` is a
 * precondition), freezeTime() and Sleep::fake() (the editor polls and streams;
 * a frozen clock hangs those turns), and the stray-request/process guards (the
 * plugin shells out to Playwright and talks to it over a socket).
 *
 * Everything about the database stays identical — the plugin's HTTP server runs
 * IN-PROCESS, building a Symfony request and handing it to this very
 * application instance, so the browser and the test share one database, one
 * config and one container.
 */
pest()->extend(TestCase::class)
    ->use(InteractsWithTenancy::class, VisitsTenantPages::class)
    ->beforeEach(function () use ($prepareDatabase): void {
        $prepareDatabase([
            // The browser holds a cookie, so the session has to survive between
            // requests — phpunit.xml's 'array' driver forgets it every time.
            'session.driver' => 'database',

            // Tenant subdomains hang off the central domain, which config/tenancy.php
            // derives from app.url. The plugin's server binds to 127.0.0.1 and
            // Chromium resolves every *.localhost name to loopback itself
            // (RFC 6761), so "acme.localhost" arrives with a Host header
            // tenancy can identify. See VisitsTenantPages::tenantUrl().
            'app.url' => 'http://localhost',
            'tenancy.identification.central_domains' => ['localhost'],
        ]);
    })
    ->afterEach($cleanUpAfterTest)
    ->in('Browser');

/*
 * Every tenant-panel test signs in as a member of a fresh tenant — that is the
 * precondition for the panel being reachable at all (a plain User::factory() has
 * no panel access; see User::canAccessPanel()).
 *
 * Bound here rather than repeated per file: it was nine byte-identical
 * `beforeEach` blocks. It has to be a directory-scoped binding rather than a
 * nested Pest.php, because a nested beforeEach does not bind in this project — see
 * the pest-testing skill.
 */
pest()->beforeEach(function (): void {
    $this->tenant = $this->actingAsTenantPanelMember();
})->in('Feature/Filament/Tenant');
