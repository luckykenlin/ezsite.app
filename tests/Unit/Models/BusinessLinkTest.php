<?php

declare(strict_types=1);

use App\Enums\LinkKind;
use App\Models\BusinessLink;
use App\Models\Tenant;
use Illuminate\Database\QueryException;

test('tenant relation returns the owning tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $link = $this->runInTenant($tenant, fn (): BusinessLink => BusinessLink::factory()->create(['tenant_id' => $tenant->id]));
    $link = BusinessLink::query()->findOrFail($link->getKey());

    expect($link->tenant->is($tenant))->toBeTrue();
});

test('kind casts to a LinkKind', function (): void {
    $tenant = Tenant::factory()->create();
    $link = $this->runInTenant($tenant, fn (): BusinessLink => BusinessLink::factory()->booksy()->create(['tenant_id' => $tenant->id]));
    $link = BusinessLink::query()->findOrFail($link->getKey());

    expect($link->kind)->toBe(LinkKind::Booksy)
        ->and($link->url)->toStartWith('https://booksy.com/');
});

test('a tenant has at most one link of each kind', function (): void {
    // The "Book now" button must never have to choose between two Booksy pages.
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function () use ($tenant): void {
        BusinessLink::factory()->create(['tenant_id' => $tenant->id, 'kind' => LinkKind::Instagram]);

        expect(fn (): BusinessLink => BusinessLink::factory()->create(['tenant_id' => $tenant->id, 'kind' => LinkKind::Instagram]))
            ->toThrow(QueryException::class);
    });
});

test('two tenants can both have a link of the same kind', function (): void {
    foreach ([Tenant::factory()->create(), Tenant::factory()->create()] as $tenant) {
        $this->runInTenant($tenant, fn (): BusinessLink => BusinessLink::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => LinkKind::Instagram,
        ]));
    }

    expect(BusinessLink::query()->where('kind', LinkKind::Instagram)->count())->toBe(2);
});

test('the ordered scope sorts by sort, then by id', function (): void {
    $tenant = Tenant::factory()->create();
    $expected = $this->runInTenant($tenant, function () use ($tenant): array {
        $last = BusinessLink::factory()->create(['tenant_id' => $tenant->id, 'kind' => LinkKind::Yelp, 'sort' => 4]);
        $tiedFirst = BusinessLink::factory()->create(['tenant_id' => $tenant->id, 'kind' => LinkKind::Instagram, 'sort' => 0]);
        $tiedSecond = BusinessLink::factory()->create(['tenant_id' => $tenant->id, 'kind' => LinkKind::TikTok, 'sort' => 0]);

        return [$tiedFirst->id, $tiedSecond->id, $last->id];
    });

    expect(BusinessLink::query()->ordered()->pluck('id')->all())->toBe($expected);
});

test('to array', function (): void {
    $tenant = Tenant::factory()->create();
    $link = $this->runInTenant($tenant, fn (): BusinessLink => BusinessLink::factory()->create(['tenant_id' => $tenant->id]));
    $link = BusinessLink::query()->findOrFail($link->getKey());

    expect(array_keys($link->toArray()))
        ->toBe([
            'id',
            'tenant_id',
            'kind',
            'url',
            'sort',
            'created_at',
            'updated_at',
        ]);
});
