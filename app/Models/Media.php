<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\RequiresTenantContext;
use Awcodes\Curator\Models\Media as CuratorMedia;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The tenant media library entry (Curator's model, RLS-adapted). Isolation
 * is enforced entirely by RLS via the table's own tenant_id — no PHP scope,
 * same pattern as {@see Page}. Curator's Filament-panel tenancy feature is
 * NOT used; tenant_id is stamped here on create instead, since uploads
 * always happen inside an initialized tenant context (the tenant panel).
 */
final class Media extends CuratorMedia
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    use RequiresTenantContext;

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    protected static function booted(): void
    {
        self::creating(function (self $media): void {
            $tenantId = tenant('id');

            // getAttribute: the vendor @property types tenant_id as a
            // non-nullable string, but during `creating` it is genuinely
            // unset until stamped here.
            if ($media->getAttribute('tenant_id') === null && is_string($tenantId)) {
                $media->setAttribute('tenant_id', $tenantId);
            }
        });
    }

    protected static function newFactory(): MediaFactory
    {
        return MediaFactory::new();
    }
}
