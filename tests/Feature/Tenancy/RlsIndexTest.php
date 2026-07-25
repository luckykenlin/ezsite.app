<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('every rls-protected table indexes tenant_id so the injected predicate avoids a sequential scan', function (): void {
    // Derived from the live policy set — fail-closed, like RlsPolicyTest's
    // guards: a new RLS-protected table is checked here automatically instead
    // of waiting for someone to extend a hand-kept list.
    $rlsTables = collect(DB::select('SELECT DISTINCT tablename FROM pg_policies'))->pluck('tablename');

    // Sanity check: discovery found the known RLS tables, so an empty
    // pg_policies can't make this pass vacuously.
    expect($rlsTables->all())->toContain('posts', 'businesses');

    foreach ($rlsTables as $table) {
        $leadsWithTenantId = collect(Schema::getIndexes($table))
            ->contains(fn (array $index): bool => ($index['columns'][0] ?? null) === 'tenant_id');

        expect($leadsWithTenantId)->toBeTrue(sprintf('Table [%s] must carry an index leading with tenant_id for RLS.', $table));
    }
});

test('locations carries a (tenant_id, business_id) index for the business relation lookup', function (): void {
    $hasComposite = collect(Schema::getIndexes('locations'))
        ->contains(fn (array $index): bool => array_slice($index['columns'], 0, 2) === ['tenant_id', 'business_id']);

    expect($hasComposite)->toBeTrue();
});
