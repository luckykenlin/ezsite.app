<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Tenant;
use Closure;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;

/**
 * The single sanctioned entry point for any tenant-scoped write that does not
 * already run inside an HTTP request bound to a tenant domain.
 *
 * Because the central DB role bypasses RLS (required for cross-tenant reads in
 * the agency cockpit), a write performed in central context is UNPROTECTED and
 * would silently land under the wrong tenant. This wrapper forces the RLS user
 * + `my.current_tenant` session var to be active for the duration of $callback,
 * asserts it actually took effect, and always reverts — even on exception,
 * restoring the previous context (nested-safe, not a blind end-to-central).
 *
 * Caveat: a model instantiated inside $callback is stamped with the RLS
 * `tenant` connection, which PostgresRLSBootstrapper purges on revert. Do not
 * run further queries (relations, save) through a returned instance in the
 * caller's context — return a scalar/id and re-fetch, or finish the work inside
 * the callback.
 *
 * @see \App\Concerns\RequiresTenantContext runtime guard (belt-and-suspenders)
 */
final readonly class RunInTenant
{
    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function handle(Tenant|string $tenant, Closure $callback): mixed
    {
        $tenant = $tenant instanceof Tenant
            ? $tenant
            : Tenant::query()->findOrFail($tenant);

        $previous = tenant();

        tenancy()->initialize($tenant);

        try {
            $this->assertRlsContextMatches($tenant);

            return $callback();
        } finally {
            if ($previous instanceof TenantContract) {
                tenancy()->initialize($previous);
            } else {
                tenancy()->end();
            }
        }
    }

    /**
     * Verify the RLS session variable is actually set to this tenant on the
     * live connection. Turns a mis-bootstrapped context into a loud failure
     * before any write happens, instead of a silent cross-tenant write.
     */
    private function assertRlsContextMatches(TenantContract $tenant): void
    {
        $sessionVariable = Config::string('tenancy.rls.session_variable_name');

        $active = DB::scalar("SELECT current_setting('".$sessionVariable."', true)");
        $active = is_string($active) ? $active : null;

        if ($active !== (string) $tenant->getTenantKey()) {
            // @codeCoverageIgnoreStart
            // Defensive: tenancy()->initialize() SETs this variable from the same
            // tenant immediately before this check, so a mismatch only occurs if the
            // RLS bootstrapper is mis-configured — unreachable through the normal path.
            throw new RuntimeException(
                'RLS context assertion failed: expected tenant '.$tenant->getTenantKey().", got '".($active ?? 'null')."'.",
            );
            // @codeCoverageIgnoreEnd
        }
    }
}
