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

test('stock provenance columns round-trip through the factory stock state', function (): void {
    $tenant = Tenant::factory()->create();
    $media = $this->runInTenant($tenant, fn (): Media => Media::factory()->stock()->create(['tenant_id' => $tenant->id]));

    $media = Media::query()->findOrFail($media->getKey());

    expect($media->getAttribute('source_provider'))->toBe('pexels')
        ->and($media->getAttribute('source_id'))->not->toBeNull()
        ->and($media->getAttribute('photographer_name'))->not->toBeNull()
        ->and($media->directory)->toBe('stock');
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
