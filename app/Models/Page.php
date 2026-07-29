<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\RequiresTenantContext;
use App\Enums\PageStatus;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stringable;
use Z3d0X\FilamentFabricator\Models\Page as FabricatorPage;

/**
 * @property int $id
 * @property string $tenant_id
 * @property PageStatus $status
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property int|null $seo_image_media_id
 * @property bool $is_indexable
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

    public function isDraft(): bool
    {
        return $this->status === PageStatus::Draft;
    }

    /**
     * @return array<string, string|Stringable>
     */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'status' => PageStatus::class,
            'is_indexable' => 'boolean',
        ];
    }
}
