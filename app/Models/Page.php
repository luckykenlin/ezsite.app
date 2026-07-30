<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PageStatus;
use App\Tenancy\RequiresTenantContext;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stringable;
use Z3d0X\FilamentFabricator\Models\Page as FabricatorPage;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $title
 * @property string $slug
 * @property int|null $parent_id
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
     * The site's front page: slug `/` at the top level.
     *
     * Both halves matter — a nested page may legitimately be slugged `/`, so the
     * parent check is what makes this the ROOT. That pair was written out in three
     * places, one of which omitted the parent check.
     */
    public function isHome(): bool
    {
        return $this->slug === '/' && $this->parent_id === null;
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
