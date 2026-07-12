<?php

declare(strict_types=1);

use App\Models\Domain;

test('to array', function (): void {
    $domain = Domain::factory()->create();
    $domain = Domain::query()->findOrFail($domain->getKey());

    expect(array_keys($domain->toArray()))
        ->toBe([
            'id',
            'domain',
            'tenant_id',
            'created_at',
            'updated_at',
        ]);
});
