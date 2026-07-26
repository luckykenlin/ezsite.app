<?php

declare(strict_types=1);

use App\Models\Media;
use App\Models\Tenant;

test('tenant relation returns the owning tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $media = $this->runInTenant($tenant, fn (): Media => Media::factory()->create(['tenant_id' => $tenant->id]));

    $media = Media::query()->findOrFail($media->getKey());

    expect($media->tenant->is($tenant))->toBeTrue();
});

test('tenant_id is stamped from the current tenant context on create', function (): void {
    $tenant = Tenant::factory()->create();

    $id = $this->runInTenant($tenant, fn () => Media::factory()->create(['tenant_id' => null])->getKey());

    expect(Media::query()->findOrFail($id)->tenant_id)->toBe($tenant->id);
});

test('numeric metadata is cast to integers', function (): void {
    $tenant = Tenant::factory()->create();
    $media = $this->runInTenant($tenant, fn (): Media => Media::factory()->create([
        'tenant_id' => $tenant->id,
        'width' => '800',
        'height' => '600',
        'size' => '1024',
    ]));

    $media = Media::query()->findOrFail($media->getKey());

    expect($media->width)->toBe(800)
        ->and($media->height)->toBe(600)
        ->and($media->size)->toBe(1024);
});

test('the url resolves through the tenant-aware public disk', function (): void {
    $tenant = Tenant::factory()->create();

    $url = $this->runInTenant($tenant, function () use ($tenant): string {
        $media = Media::factory()->create(['tenant_id' => $tenant->id, 'path' => 'media/shopfront.jpg']);

        return $media->url;
    });

    // The tenancy url_override maps the public disk to /public-{tenant}/…
    expect($url)->toContain('public-'.$tenant->id)
        ->and($url)->toContain('media/shopfront.jpg');
});
