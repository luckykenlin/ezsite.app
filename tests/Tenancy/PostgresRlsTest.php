<?php

declare(strict_types=1);

use App\Models\Page;
use App\Models\Post;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Commands\CreateUserWithRLSPolicies;
use Stancl\Tenancy\RLS\PolicyManagers\RLSPolicyManager;

// No beforeEach call to `tenants:rls` here: TenancyServiceProvider::syncRlsPoliciesAfterMigrations()
// runs it automatically after the migrate:fresh in tests/Pest.php's Tenancy group setup.

test('rls policies exist for every table with a path to the tenants table, and only those tables', function (): void {
    /** @var RLSPolicyManager $manager */
    $manager = resolve(config()->string('tenancy.rls.manager'));
    $queries = $manager->generateQueries();

    $expectedTables = collect(array_keys($queries));
    $actualTables = collect(DB::select('SELECT tablename FROM pg_policies'))->pluck('tablename');

    expect($actualTables->sort()->values()->all())->toBe($expectedTables->sort()->values()->all())
        ->and($expectedTables)->toContain('posts')
        ->and($expectedTables)->toContain('pages')
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

test('a tenant can only see its own posts once tenancy is initialized', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    tenancy()->initialize($tenantA);
    $post = Post::query()->create(['tenant_id' => $tenantA->id, 'title' => 'Post from A']);
    expect($post->tenant->is($tenantA))->toBeTrue();
    tenancy()->end();

    tenancy()->initialize($tenantB);
    Post::query()->create(['tenant_id' => $tenantB->id, 'title' => 'Post from B']);

    expect(Post::query()->count())->toBe(1)
        ->and(Post::query()->sole()->title)->toBe('Post from B');
    tenancy()->end();

    tenancy()->initialize($tenantA);
    expect(Post::query()->count())->toBe(1)
        ->and(Post::query()->sole()->title)->toBe('Post from A');
    tenancy()->end();
});

test('the central connection sees posts across all tenants', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    tenancy()->initialize($tenantA);
    Post::query()->create(['tenant_id' => $tenantA->id, 'title' => 'Post from A']);
    tenancy()->end();

    tenancy()->initialize($tenantB);
    Post::query()->create(['tenant_id' => $tenantB->id, 'title' => 'Post from B']);
    tenancy()->end();

    expect(Post::query()->count())->toBe(2);
});

test('users stay global and are unaffected by tenancy', function (): void {
    $tenant = Tenant::factory()->create();

    User::factory()->create(['name' => 'Central User']);

    tenancy()->initialize($tenant);
    expect(User::query()->where('name', 'Central User')->exists())->toBeTrue();
    tenancy()->end();
});

test('a tenant can only see its own pages once tenancy is initialized', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    tenancy()->initialize($tenantA);
    $page = Page::query()->create(['tenant_id' => $tenantA->id, 'title' => 'Home', 'slug' => '/', 'layout' => 'main', 'blocks' => []]);
    expect($page->tenant->is($tenantA))->toBeTrue();
    tenancy()->end();

    tenancy()->initialize($tenantB);
    Page::query()->create(['tenant_id' => $tenantB->id, 'title' => 'Home', 'slug' => '/', 'layout' => 'main', 'blocks' => []]);

    expect(Page::query()->count())->toBe(1)
        ->and(Page::query()->sole()->tenant_id)->toBe($tenantB->id);
    tenancy()->end();

    tenancy()->initialize($tenantA);
    expect(Page::query()->count())->toBe(1)
        ->and(Page::query()->sole()->tenant_id)->toBe($tenantA->id);
    tenancy()->end();
});

test('rls enforcement breaks writes once the session variable is reset', function (): void {
    $tenant = Tenant::factory()->create();

    tenancy()->initialize($tenant);

    DB::statement('RESET '.config('tenancy.rls.session_variable_name'));

    expect(fn () => Post::query()->create(['tenant_id' => $tenant->id, 'title' => 'should fail']))
        ->toThrow(QueryException::class);

    tenancy()->end();
});
