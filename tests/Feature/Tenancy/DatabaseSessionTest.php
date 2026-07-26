<?php

declare(strict_types=1);

use App\Models\Tenant;

/**
 * DatabaseSessionBootstrapper resolves the `tenant` connection *by name*, and
 * PostgresRLSBootstrapper is what defines that connection. Bootstrappers run in
 * `tenancy.bootstrappers` order, so listing the session one first makes every
 * tenancy init that happens after the database session driver was resolved
 * (i.e. inside any authenticated request) fail with
 * "Database connection [tenant] not configured."
 */
beforeEach(function (): void {
    config()->set('session.driver', 'database');

    // StartSession resolves the driver on every real request; the array driver
    // is used in tests, so resolve it explicitly to reproduce that state.
    app('session')->driver('database');
});

test('the database session handler follows tenancy into tenant context and back', function (): void {
    $tenant = Tenant::factory()->create();

    $connectionInTenantContext = tenancy()->run($tenant, fn (): string => config()->string('session.connection'));

    expect($connectionInTenantContext)->toBe('tenant')
        ->and(config('session.connection'))->toBe(config('tenancy.database.central_connection'));
});
