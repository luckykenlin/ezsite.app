<?php

declare(strict_types=1);

use App\Models\Page;
use App\Models\Tenant;
use Illuminate\Database\QueryException;

test('tenant relation returns the owning tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $page = $this->runInTenant($tenant, fn (): Page => Page::factory()->create(['tenant_id' => $tenant->id]));

    expect($page->tenant->is($tenant))->toBeTrue();
});

test('rejects a duplicate root slug within a tenant', function (): void {
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function () use ($tenant): void {
        Page::factory()->create(['tenant_id' => $tenant->id, 'slug' => '/', 'parent_id' => null]);

        // Without NULLS NOT DISTINCT this second insert would succeed, because Postgres
        // treats the two NULL parent_id values as distinct and skips the unique check.
        expect(fn () => Page::factory()->create(['tenant_id' => $tenant->id, 'slug' => '/', 'parent_id' => null]))
            ->toThrow(QueryException::class);
    });
});

test('allows the same slug under different parents within a tenant', function (): void {
    $tenant = Tenant::factory()->create();

    $child = $this->runInTenant($tenant, function () use ($tenant): Page {
        $root = Page::factory()->create(['tenant_id' => $tenant->id, 'slug' => 'about', 'parent_id' => null]);

        return Page::factory()->create(['tenant_id' => $tenant->id, 'slug' => 'about', 'parent_id' => $root->id]);
    });

    expect($child->exists)->toBeTrue();
});

test('to array', function (): void {
    $tenant = Tenant::factory()->create();
    $page = $this->runInTenant($tenant, fn (): Page => Page::factory()->create(['tenant_id' => $tenant->id]));
    $page = Page::query()->findOrFail($page->getKey());

    expect(array_keys($page->toArray()))
        ->toBe([
            'id',
            'tenant_id',
            'title',
            'slug',
            'layout',
            'blocks',
            'parent_id',
            'created_at',
            'updated_at',
        ]);
});
