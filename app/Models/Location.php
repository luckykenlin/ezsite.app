<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\OpeningHours as OpeningHoursCast;
use App\Tenancy\RequiresTenantContext;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\OpeningHours\OpeningHours;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $business_id
 * @property string $label
 * @property bool $is_primary
 * @property string|null $address_line1
 * @property string|null $address_line2
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postal_code
 * @property string|null $country
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $timezone
 * @property OpeningHours|null $opening_hours
 * @property string $status
 *
 * @method static LocationFactory factory($count = null, $state = [])
 */
final class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    use RequiresTenantContext;
    use SoftDeletes;

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Primary location first, then oldest — the order every "which location?"
     * read uses.
     *
     * Named because three places have to agree on it: what a bound block RENDERS
     * ({@see \App\Site\BindResolver::location()}), the order of the location
     * PICKER's options, and the digest the site-draft prompt sees. They were three
     * identical literals; a disagreement would have shown up as a block rendering
     * one location while its picker defaulted to another.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function primaryFirst(Builder $query): void
    {
        $query->orderByDesc('is_primary')->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'opening_hours' => OpeningHoursCast::class,
        ];
    }
}
