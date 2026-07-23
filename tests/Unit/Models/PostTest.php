<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\Tenant;

test('tenant relation returns the owning tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $post = $this->runInTenant($tenant, fn (): Post => Post::factory()->create(['tenant_id' => $tenant->id]));

    expect($post->tenant->is($tenant))->toBeTrue();
});

test('to array', function (): void {
    $tenant = Tenant::factory()->create();
    $post = $this->runInTenant($tenant, fn (): Post => Post::factory()->create(['tenant_id' => $tenant->id]));
    $post = Post::query()->findOrFail($post->getKey());

    expect(array_keys($post->toArray()))
        ->toBe([
            'id',
            'tenant_id',
            'title',
            'body',
            'created_at',
            'updated_at',
        ]);
});
