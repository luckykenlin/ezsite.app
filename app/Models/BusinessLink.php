<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LinkKind;
use App\Tenancy\RequiresTenantContext;
use Carbon\CarbonImmutable;
use Database\Factories\BusinessLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One outbound link the business owns: its Booksy page, its Instagram, its
 * Google Business Profile.
 *
 * A row per link keyed by {@see LinkKind} rather than a column per platform on
 * `businesses`: the platform list grows with every vertical (booking apps for
 * salons, DoorDash for restaurants), and a kind is what lets the site decide
 * which link becomes the "Book now" button without parsing URLs. One link per
 * kind per tenant, enforced by a unique index.
 *
 * RLS-only isolation, writes guarded by {@see RequiresTenantContext}.
 *
 * @property int $id
 * @property string $tenant_id
 * @property LinkKind $kind
 * @property string $url
 * @property int $sort
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static BusinessLinkFactory factory($count = null, $state = [])
 */
final class BusinessLink extends Model
{
    /** @use HasFactory<BusinessLinkFactory> */
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
            'kind' => LinkKind::class,
            'sort' => 'integer',
        ];
    }
}
