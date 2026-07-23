<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\RequiresTenantContext;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Z3d0X\FilamentFabricator\Models\Page as FabricatorPage;

/**
 * @property string $tenant_id
 *
 * @method static PageFactory factory($count = null, $state = [])
 */
final class Page extends FabricatorPage
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    use RequiresTenantContext;

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
