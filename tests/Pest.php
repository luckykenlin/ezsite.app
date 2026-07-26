<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Stancl\Tenancy\Events\TenantCreated;
use Tests\Concerns\InteractsWithTenancy;
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

pest()->extend(TestCase::class)
    ->use(InteractsWithTenancy::class)
    ->beforeEach(function () use (&$createdTenantKeys): void {
        $createdTenantKeys = [];

        Event::listen(TenantCreated::class, function (TenantCreated $event) use (&$createdTenantKeys): void {
            $createdTenantKeys[] = (string) $event->tenant->getTenantKey();
        });

        Str::createRandomStringsNormally();
        Str::createUuidsNormally();
        Http::preventStrayRequests();
        Process::preventStrayProcesses();
        Sleep::fake();

        $this->freezeTime();

        $baseDatabase = 'ezsite_testing';
        $token = ParallelTesting::token();
        $database = $token ? sprintf('%s_test_%s', $baseDatabase, $token) : $baseDatabase;

        config([
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
            // "{suffix_base}{tenant_id}" under the shared storage_path(). Parallel
            // runners share that path, so scope the base per process token — the same
            // isolation the per-token database name gives above. Without this, one
            // process's afterEach cleanup deletes a live tenant dir owned by another
            // process, and its next storage write throws mid-test.
            'tenancy.filesystem.suffix_base' => $token ? sprintf('tenant_token%s_', $token) : 'tenant',
        ]);

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
    })
    ->afterEach(function () use (&$createdTenantKeys): void {
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
    })
    ->in('Feature', 'Unit');
