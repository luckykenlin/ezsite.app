<?php

declare(strict_types=1);

use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Commands\CreateUserWithRLSPolicies;
use Stancl\Tenancy\RLS\PolicyManagers\RLSPolicyManager;

// No beforeEach call to `tenants:rls` here: TenancyServiceProvider::syncRlsPoliciesAfterMigrations()
// runs it automatically after the migrate:fresh in tests/Pest.php's setup.

test('rls policies exist for every table with a path to the tenants table, and only those tables', function (): void {
    /** @var RLSPolicyManager $manager */
    $manager = resolve(config()->string('tenancy.rls.manager'));
    $queries = $manager->generateQueries();

    $expectedTables = collect(array_keys($queries));
    $actualTables = collect(DB::select('SELECT tablename FROM pg_policies'))->pluck('tablename');

    expect($actualTables->sort()->values()->all())->toBe($expectedTables->sort()->values()->all())
        ->and($expectedTables)->toContain('posts')
        ->and($expectedTables)->toContain('pages')
        ->and($expectedTables)->toContain('locations')
        ->and($expectedTables)->toContain('businesses')
        ->and($expectedTables)->not->toContain('users')
        ->and($expectedTables)->not->toContain('domains')
        ->and($expectedTables)->not->toContain('tenant_user');

    $hasher = resolve(CreateUserWithRLSPolicies::class);

    foreach ($queries as $table => $query) {
        [$hash] = $hasher->hashPolicy($query);

        $policy = collect(DB::select('SELECT policyname FROM pg_policies WHERE tablename = ?', [$table]))->first();

        expect($policy?->policyname)->toBe(sprintf('%s_rls_policy_%s', $table, $hash));
    }
});

test('rls policy sync only runs on forward migrations, not rollbacks', function (): void {
    Artisan::spy();

    event(new MigrationsEnded('down'));

    Artisan::shouldNotHaveReceived('call', ['tenants:rls']);
});

/*
 * Fail-CLOSED coverage guard. RLS auto-generation is fail-OPEN by nature: a table
 * with no foreign-key path to `tenants` silently gets no policy, so a new
 * tenant-owned table that forgets its tenant_id FK would leak across tenants with
 * every test still green. This flips that default — every table must EITHER have
 * an RLS policy OR be listed here as a conscious exemption with a reason. A new
 * unlisted, unprotected table fails this test loudly.
 */
test('every table is RLS-protected or an explicitly documented exemption', function (): void {
    $exempt = [
        // Framework-owned, no tenant data.
        'migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
        'password_reset_tokens', 'sessions',
        // Global identity — users are shared across tenants, not tenant-owned (see tenancy.md).
        'users',
        // The tenant table itself, and the two 'no-rls' tables that must be queryable
        // before tenancy resolves / from the central panel (domains.tenant_id, tenant_user.tenant_id).
        'tenants', 'domains', 'tenant_user',
    ];

    $allTables = collect(DB::select('SELECT tablename FROM pg_tables WHERE schemaname = current_schema()'))
        ->pluck('tablename');
    $protectedTables = collect(DB::select('SELECT DISTINCT tablename FROM pg_policies'))
        ->pluck('tablename');

    $unaccountedFor = $allTables->diff($protectedTables)->diff($exempt)->sort()->values()->all();

    expect($unaccountedFor)->toBe([], sprintf(
        'These tables have no RLS policy and are not on the exemption allowlist: %s. '
        .'Add a tenant_id FK so they are auto-scoped, or add them to $exempt with a documented reason.',
        implode(', ', $unaccountedFor),
    ));
});

/*
 * Enforces the "single-hop" convention (see tenancy.md): every tenant-owned table
 * carries its OWN non-nullable tenant_id with a direct FK to `tenants`, so its
 * policy is a flat `WHERE tenant_id = current_setting(...)` rather than a correlated
 * subquery through a parent table. Keeps policies fast and indexable on the shared DB.
 */
test('every RLS-protected table scopes through its own tenant_id in a single hop', function (): void {
    /** @var RLSPolicyManager $manager */
    $manager = resolve(config()->string('tenancy.rls.manager'));

    foreach ($manager->shortestPaths() as $table => $path) {
        expect($path)->toHaveCount(1, sprintf("Table '%s' scopes through a multi-hop path; give it a direct tenant_id FK.", $table))
            ->and($path[0]['foreignTable'])->toBe('tenants')
            ->and($path[0]['localColumn'])->toBe('tenant_id');
    }
});

/*
 * Deploy canary. `tenants:rls` runs synchronously after every forward pgsql
 * migration (TenancyServiceProvider::syncRlsPoliciesAfterMigrations). Recursive or
 * composite foreign keys, or malformed 'rls' comment constraints, make policy
 * generation throw — which would abort the migration and break a production deploy.
 * Assert generation stays clean so that failure surfaces in CI, not in prod.
 */
test('rls policy generation succeeds for the current schema', function (): void {
    /** @var RLSPolicyManager $manager */
    $manager = resolve(config()->string('tenancy.rls.manager'));

    expect(fn () => $manager->generateQueries())->not->toThrow(Exception::class);
});
