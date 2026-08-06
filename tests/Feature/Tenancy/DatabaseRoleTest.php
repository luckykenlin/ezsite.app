<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/*
 * Load-bearing invariant for the whole suite: non-tenancy tests create rows in
 * RLS-protected tables (posts/pages/locations) WITHOUT calling
 * tenancy()->initialize(). That only works because the test connection's role
 * bypasses RLS (a SUPERUSER, or a role with BYPASSRLS). If the test database
 * role loses that, those tests would fail with opaque RLS policy violations
 * rather than a clear cause — so assert the invariant explicitly and loudly.
 */
test('the test database role can bypass RLS', function (): void {
    $role = DB::selectOne(
        'SELECT (rolsuper OR rolbypassrls)::int AS can_bypass FROM pg_roles WHERE rolname = current_user'
    );

    expect((int) $role->can_bypass)->toBe(
        1,
        'The test DB role ('.DB::getConfig('username').') must be a SUPERUSER or have BYPASSRLS: '
        .'non-tenancy tests write to RLS-protected tables without initializing tenancy and rely on bypassing RLS.'
    );
});
