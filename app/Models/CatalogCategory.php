<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\RequiresTenantContext;
use Carbon\CarbonImmutable;
use Database\Factories\CatalogCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A heading on the business's catalogue — "Manicures", "Acrylics", "Add-ons"
 * for a nail salon; a menu section for a restaurant. What the catalogue is
 * called is the vertical's business ({@see \App\Enums\Vertical::catalogLabel()}),
 * so the table is named for the shape, not for any one vertical.
 *
 * Its own table rather than a string column on each item: categories carry an
 * order and a description of their own, and the gallery filters by them.
 *
 * RLS-only isolation like every tenant-owned model: no global scope, writes
 * guarded by {@see RequiresTenantContext}.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $name
 * @property string|null $description
 * @property int $sort
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static CatalogCategoryFactory factory($count = null, $state = [])
 */
final class CatalogCategory extends Model
{
    /** @use HasFactory<CatalogCategoryFactory> */
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
     * The category's items in operator order — the only order anything ever
     * reads them in, so it is part of the relation rather than left to each
     * caller.
     *
     * @return HasMany<CatalogItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CatalogItem::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * @return HasMany<GalleryImage, $this>
     */
    public function galleryImages(): HasMany
    {
        return $this->hasMany(GalleryImage::class);
    }

    /**
     * Operator order, with `id` as the tiebreak so rows sharing a `sort` (every
     * row starts at 0) come back in a stable order.
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
