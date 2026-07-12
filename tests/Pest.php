<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        Str::createRandomStringsNormally();
        Str::createUuidsNormally();
        Http::preventStrayRequests();
        Process::preventStrayProcesses();
        Sleep::fake();

        $this->freezeTime();
    })
    ->in('Browser', 'Feature', 'Unit');

/*
 * Postgres RLS can't be exercised on the sqlite connection the rest of the
 * suite uses, so this group runs against a dedicated real Postgres database
 * instead of relying on RefreshDatabase/sqlite.
 */
pest()->extend(TestCase::class)
    ->beforeEach(function (): void {
        $baseDatabase = 'ezsite_testing';
        $token = ParallelTesting::token();
        $database = $token ? sprintf('%s_test_%s', $baseDatabase, $token) : $baseDatabase;

        config([
            'database.default' => 'pgsql',
            'database.connections.pgsql.database' => $database,
            // The package's CentralConnection trait always queries whatever connection
            // this key names, regardless of database.default, so it must point at pgsql too.
            'tenancy.database.central_connection' => 'pgsql',
            // CacheTenancyBootstrapper doesn't support the 'array' store the rest of the
            // suite uses for speed, so use the real 'database' store here instead.
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
        // FilesystemTenancyBootstrapper creates storage/{tenant} directories as a
        // side effect of initializing tenancy; clean them up so they don't pile up.
        foreach (File::glob(storage_path('tenant*')) as $tenantStoragePath) {
            File::deleteDirectory($tenantStoragePath);
        }
    })
    ->in('Tenancy');
