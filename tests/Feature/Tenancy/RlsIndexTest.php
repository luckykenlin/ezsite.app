<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

test('every rls-scoped table indexes tenant_id so the injected predicate avoids a sequential scan', function (string $table): void {
    $leadsWithTenantId = collect(Schema::getIndexes($table))
        ->contains(fn (array $index): bool => ($index['columns'][0] ?? null) === 'tenant_id');

    expect($leadsWithTenantId)->toBeTrue(sprintf('Table [%s] must carry an index leading with tenant_id for RLS.', $table));
})->with(['posts', 'pages', 'locations']);

test('locations carries a (tenant_id, business_id) index for the business relation lookup', function (): void {
    $hasComposite = collect(Schema::getIndexes('locations'))
        ->contains(fn (array $index): bool => array_slice($index['columns'], 0, 2) === ['tenant_id', 'business_id']);

    expect($hasComposite)->toBeTrue();
});
