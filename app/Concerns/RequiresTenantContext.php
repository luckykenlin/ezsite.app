<?php

declare(strict_types=1);

namespace App\Concerns;

use RuntimeException;

/**
 * Guard for RLS-scoped tenant models: refuse to create/update/delete/restore
 * unless tenancy is initialized (i.e. we're on the RLS connection with
 * `my.current_tenant` set), so a forgotten RunInTenant fails loud instead of
 * writing under the BYPASSRLS central connection with no tenant scope.
 *
 * Reads are intentionally NOT guarded — the central cockpit legitimately reads
 * across tenants on the BYPASSRLS connection.
 *
 * @see \App\Actions\RunInTenant the sanctioned write channel this guard steers towards
 */
trait RequiresTenantContext
{
    public static function bootRequiresTenantContext(): void
    {
        // Register through registerModelEvent (what the `creating()`/`updating()`
        // helpers call internally) rather than the magic `static::$event()`: the
        // magic `restoring` call only exists on SoftDeletes models and would trip
        // model boot elsewhere. `restoring` simply never fires on a non-SoftDeletes
        // model, so registering it unconditionally is harmless.
        foreach (['creating', 'updating', 'deleting', 'restoring'] as $event) {
            static::registerModelEvent($event, function (self $model): void {
                if (! tenant()) {
                    throw new RuntimeException(sprintf(
                        '%s is RLS-scoped and can only be written inside a tenant context. '
                        .'Wrap the write in RunInTenant (see docs/tenant-write-context.md).',
                        $model::class,
                    ));
                }
            });
        }
    }
}
