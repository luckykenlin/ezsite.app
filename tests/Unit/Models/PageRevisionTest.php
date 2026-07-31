<?php

declare(strict_types=1);

use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;

test('belongs to a tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $revision = $this->runInTenant($tenant, fn (): PageRevision => PageRevision::factory()->create([
        'tenant_id' => $tenant->id,
    ]));

    expect(PageRevision::query()->findOrFail($revision->getKey())->tenant->is($tenant))->toBeTrue();
});

test('belongs to a page', function (): void {
    $tenant = Tenant::factory()->create();
    $page = $this->createTenantPage($tenant, []);
    $revision = $this->runInTenant($tenant, fn (): PageRevision => PageRevision::factory()->create([
        'tenant_id' => $tenant->id,
        'page_id' => $page->id,
    ]));

    // Compared against a freshly queried page, not the instance createTenantPage
    // returned: that one still carries the `tenant` connection tenancy installed,
    // and Model::is() compares connection as well as key.
    $stored = PageRevision::query()->findOrFail($revision->getKey());

    expect($stored->page->is(Page::query()->findOrFail($stored->page_id)))->toBeTrue();
});

test('belongs to the user who saved it', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $revision = $this->runInTenant($tenant, fn (): PageRevision => PageRevision::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
    ]));

    expect(PageRevision::query()->findOrFail($revision->getKey())->user->is($user))->toBeTrue();
});

test('goes with the page it describes', function (): void {
    // A revision of a page that no longer exists cannot be restored into
    // anything, so the FK cascades.
    $tenant = Tenant::factory()->create();
    $page = $this->createTenantPage($tenant, []);

    $this->runInTenant($tenant, function () use ($tenant, $page): void {
        PageRevision::factory()->create(['tenant_id' => $tenant->id, 'page_id' => $page->id]);
        Page::query()->whereKey($page->getKey())->delete();
    });

    expect(PageRevision::query()->count())->toBe(0);
});

test('survives the user who saved it being removed', function (): void {
    // Attribution must not punch holes in the history.
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $revision = $this->runInTenant($tenant, fn (): PageRevision => PageRevision::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
    ]));

    $user->delete();

    expect(PageRevision::query()->findOrFail($revision->getKey())->user_id)->toBeNull();
});

test('counts the blocks it holds', function (): void {
    $tenant = Tenant::factory()->create();
    $revision = $this->runInTenant($tenant, fn (): PageRevision => PageRevision::factory()->create([
        'tenant_id' => $tenant->id,
        'blocks' => [
            ['type' => 'hero', 'data' => []],
            ['type' => 'cta', 'data' => []],
        ],
    ]));

    expect(PageRevision::query()->findOrFail($revision->getKey())->blockCount())->toBe(2);
});

test('is never updated', function (): void {
    // The table has no updated_at: a revision records a moment and is only ever
    // inserted, read and pruned.
    expect(PageRevision::UPDATED_AT)->toBeNull()
        ->and(Schema::hasColumn('page_revisions', 'updated_at'))->toBeFalse();
});

test('casts blocks to an array and the timestamp to a date', function (): void {
    $tenant = Tenant::factory()->create();
    $revision = $this->runInTenant($tenant, fn (): PageRevision => PageRevision::factory()->create([
        'tenant_id' => $tenant->id,
        'blocks' => [['type' => 'hero', 'data' => ['heading' => 'Hi']]],
    ]));

    $stored = PageRevision::query()->findOrFail($revision->getKey());

    expect($stored->blocks)->toBe([['type' => 'hero', 'data' => ['heading' => 'Hi']]])
        ->and($stored->created_at)->toBeInstanceOf(CarbonImmutable::class);
});

test('to array', function (): void {
    $tenant = Tenant::factory()->create();
    $revision = $this->runInTenant($tenant, fn (): PageRevision => PageRevision::factory()->create([
        'tenant_id' => $tenant->id,
    ]));

    expect(array_keys(PageRevision::query()->findOrFail($revision->getKey())->toArray()))
        ->toBe([
            'id',
            'tenant_id',
            'page_id',
            'user_id',
            'blocks',
            'created_at',
        ]);
});
