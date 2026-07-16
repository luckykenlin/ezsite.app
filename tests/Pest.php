<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
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
pest()->extend(TestCase::class)
    ->use(InteractsWithTenancy::class)
    ->beforeEach(function (): void {
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
    ->afterEach(function (): void {
        // End tenancy so a test that leaves it initialized can't leak the RLS
        // session context into the next test. Idempotent when already ended.
        tenancy()->end();

        // FilesystemTenancyBootstrapper creates storage/{tenant} directories as a
        // side effect of initializing tenancy; clean them up so they don't pile up.
        // Parallel runners share this path, so a directory can vanish between the
        // glob and the delete — ignore the resulting race rather than fail the test.
        foreach (File::glob(storage_path('tenant*')) as $tenantStoragePath) {
            rescue(fn () => File::deleteDirectory($tenantStoragePath), report: false);
        }
    })
    ->in('Feature', 'Unit');
