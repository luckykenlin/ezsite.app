<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
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

test('a new page is indexable and carries no seo overrides', function (): void {
    $tenant = Tenant::factory()->create();
    $page = $this->runInTenant($tenant, fn (): Page => Page::factory()->create(['tenant_id' => $tenant->id]));
    $page = Page::query()->findOrFail($page->getKey());

    expect($page->is_indexable)->toBeTrue()
        ->and($page->seo_title)->toBeNull()
        ->and($page->seo_description)->toBeNull()
        ->and($page->seo_image_media_id)->toBeNull();
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
            'status',
            'parent_id',
            'created_at',
            'updated_at',
            'seo_title',
            'seo_description',
            'seo_image_media_id',
            'is_indexable',
            'draft',
            'draft_updated_at',
        ]);
});

test('the editor draft casts to an array and its timestamp to a date', function (): void {
    $tenant = Tenant::factory()->create();
    $page = $this->runInTenant($tenant, fn (): Page => Page::factory()->create(['tenant_id' => $tenant->id]));

    Page::query()->whereKey($page->getKey())->toBase()->update([
        'draft' => json_encode(['blocks' => []], JSON_THROW_ON_ERROR),
        'draft_updated_at' => now(),
    ]);

    $stored = Page::query()->findOrFail($page->getKey());

    expect($stored->draft)->toBe(['blocks' => []])
        ->and($stored->draft_updated_at)->toBeInstanceOf(CarbonImmutable::class);
});

test('status is cast to the PageStatus enum, defaulting to published', function (): void {
    $tenant = Tenant::factory()->create();
    $page = $this->runInTenant($tenant, fn (): Page => Page::factory()->create(['tenant_id' => $tenant->id]));
    $draft = $this->runInTenant($tenant, fn (): Page => Page::factory()->draft()->create(['tenant_id' => $tenant->id]));

    expect(Page::query()->findOrFail($page->getKey())->status)->toBe(PageStatus::Published)
        ->and(Page::query()->findOrFail($page->getKey())->isDraft())->toBeFalse()
        ->and(Page::query()->findOrFail($draft->getKey())->status)->toBe(PageStatus::Draft)
        ->and(Page::query()->findOrFail($draft->getKey())->isDraft())->toBeTrue();
});
