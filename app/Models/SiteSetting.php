<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\RequiresTenantContext;
use Database\Factories\SiteSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-tenant site chrome: the header/footer block entries the main layout
 * renders around every page. One row per tenant (unique tenant_id); null
 * columns fall back to the default chrome (see SiteChrome).
 *
 * @property int $id
 * @property string $tenant_id
 * @property array<int, mixed>|null $header
 * @property array<int, mixed>|null $footer
 *
 * @method static SiteSettingFactory factory($count = null, $state = [])
 */
final class SiteSetting extends Model
{
    /** @use HasFactory<SiteSettingFactory> */
    use HasFactory;

    use RequiresTenantContext;

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'header' => 'array',
            'footer' => 'array',
        ];
    }
}
