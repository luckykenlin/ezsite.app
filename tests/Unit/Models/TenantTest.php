<?php

declare(strict_types=1);

use App\Models\Tenant;

test('to array', function (): void {
    $tenant = Tenant::factory()->create();
    $tenant = Tenant::query()->findOrFail($tenant->getKey());

    expect(array_keys($tenant->toArray()))
        ->toBe([
            'id',
            'name',
            'email',
            'created_at',
            'updated_at',
            'data',
        ]);
});
