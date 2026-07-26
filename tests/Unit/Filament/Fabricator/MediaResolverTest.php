<?php

declare(strict_types=1);

use App\Filament\Fabricator\MediaResolver;
use App\Models\Media;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

it('resolves ids to urls with one batched query', function (): void {
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function () use ($tenant): void {
        [$first, $second] = Media::factory()->count(2)->create(['tenant_id' => $tenant->id]);

        $resolver = new MediaResolver;
        $queries = 0;
        DB::listen(function ($query) use (&$queries): void {
            if (str_contains((string) $query->sql, 'curator')) {
                $queries++;
            }
        });

        $resolver->preload([$first->id, (string) $second->id, null, 'abc', 999999]);

        expect($resolver->url($first->id))->toBe($first->url)
            // Digit strings (Filament dehydration) are tolerated.
            ->and($resolver->url((string) $second->id))->toBe($second->url)
            // Dangling and malformed ids resolve to null without re-querying.
            ->and($resolver->url(999999))->toBeNull()
            ->and($resolver->url('abc'))->toBeNull()
            ->and($resolver->url(null))->toBeNull()
            ->and($queries)->toBe(1);
    });
});

it('digs the id out of the CuratorPicker raw form state', function (): void {
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function () use ($tenant): void {
        $media = Media::factory()->create(['tenant_id' => $tenant->id]);

        $resolver = new MediaResolver;

        // The editor's live preview substitutes the picker's uncommitted
        // raw state: a uuid-keyed array of media item arrays.
        expect($resolver->url(['some-uuid' => ['id' => $media->id, 'name' => 'x']]))->toBe($media->url)
            ->and($resolver->url([]))->toBeNull()
            ->and($resolver->url(['some-uuid' => ['name' => 'no-id']]))->toBeNull();
    });
});

it('cannot resolve another tenant media id', function (): void {
    $acme = Tenant::factory()->create();
    $beta = Tenant::factory()->create();

    $foreignId = $this->runInTenant($acme, fn () => Media::factory()->create(['tenant_id' => $acme->id])->getKey());

    $url = $this->runInTenant($beta, fn (): ?string => (new MediaResolver)->url($foreignId));

    expect($url)->toBeNull();
});
