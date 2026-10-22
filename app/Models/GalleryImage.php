<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\RequiresTenantContext;
use Carbon\CarbonImmutable;
use Database\Factories\GalleryImageFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One photo on the business's work wall — for a nail salon, the portfolio
 * that sells the next appointment.
 *
 * A row per image rather than a list of media ids inside a gallery block, so
 * the same portfolio can feed the gallery page, a home-page strip and the
 * services page (filtered by category) without being curated three times.
 * The row exists only for its photo: deleting the media entry deletes it.
 *
 * RLS-only isolation, writes guarded by {@see RequiresTenantContext}.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $media_id
 * @property string|null $caption
 * @property int|null $catalog_category_id
 * @property int $sort
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static GalleryImageFactory factory($count = null, $state = [])
 */
final class GalleryImage extends Model
{
    /** @use HasFactory<GalleryImageFactory> */
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
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /**
     * The service category the work belongs to, if the operator tagged one —
     * what lets the wall filter "Acrylics" from "Nail art".
     *
     * @return BelongsTo<CatalogCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CatalogCategory::class, 'catalog_category_id');
    }

    /**
     * Operator order, `id` as the tiebreak.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort' => 'integer',
        ];
    }
}
