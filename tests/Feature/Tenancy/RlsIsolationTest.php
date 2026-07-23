<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Location;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('a tenant sees only its own posts once tenancy is initialized', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    tenancy()->initialize($tenantA);
    Post::query()->create(['tenant_id' => $tenantA->id, 'title' => 'Post from A']);
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

test('a tenant sees only its own pages once tenancy is initialized', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    tenancy()->initialize($tenantA);
    Page::query()->create(['tenant_id' => $tenantA->id, 'title' => 'Home', 'slug' => '/', 'layout' => 'main', 'blocks' => []]);
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

test('a tenant sees only its own locations once tenancy is initialized', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    tenancy()->initialize($tenantA);
    $businessA = Business::factory()->create(['tenant_id' => $tenantA->id]);
    Location::factory()->create(['tenant_id' => $tenantA->id, 'business_id' => $businessA->id, 'label' => 'A HQ']);
    tenancy()->end();

    tenancy()->initialize($tenantB);

    $businessB = Business::factory()->create(['tenant_id' => $tenantB->id]);
    Location::factory()->create(['tenant_id' => $tenantB->id, 'business_id' => $businessB->id, 'label' => 'B HQ']);

    expect(Location::query()->count())->toBe(1)
        ->and(Location::query()->sole()->label)->toBe('B HQ');
    tenancy()->end();

    tenancy()->initialize($tenantA);
    expect(Location::query()->count())->toBe(1)
        ->and(Location::query()->sole()->label)->toBe('A HQ');
    tenancy()->end();
});

test('the central connection sees rows across all tenants', function (): void {
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

test('a tenant sees only its own business once tenancy is initialized', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    tenancy()->initialize($tenantA);
    Business::factory()->create(['tenant_id' => $tenantA->id, 'name' => 'Acme A']);
    tenancy()->end();

    tenancy()->initialize($tenantB);
    Business::factory()->create(['tenant_id' => $tenantB->id, 'name' => 'Acme B']);

    expect(Business::query()->count())->toBe(1)
        ->and(Business::query()->sole()->name)->toBe('Acme B');
    tenancy()->end();

    tenancy()->initialize($tenantA);
    expect(Business::query()->count())->toBe(1)
        ->and(Business::query()->sole()->name)->toBe('Acme A');
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
