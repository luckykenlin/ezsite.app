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
        ->and($expectedTables)->not->toContain('users')
        ->and($expectedTables)->not->toContain('domains')
        ->and($expectedTables)->not->toContain('tenant_user')
        ->and($expectedTables)->not->toContain('businesses');

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
