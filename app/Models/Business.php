<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use NunoMaduro\LaravelSluggable\Attributes\Sluggable;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $name
 * @property string $slug
 * @property string|null $category
 * @property string|null $tagline
 * @property string|null $description
 * @property string|null $logo_path
 * @property string|null $brand_primary
 * @property string|null $brand_secondary
 * @property string|null $brand_accent
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property string|null $website_url
 * @property string|null $timezone
 * @property string|null $locale
 * @property string|null $currency
 * @property string $status
 *
 * @method static BusinessFactory factory($count = null, $state = [])
 */
#[Sluggable(from: 'name', scope: 'tenant_id')]
final class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return HasMany<Location, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    /**
     * Cascade soft-deletes and restores to the business's locations so a
     * soft-deleted business never leaves orphaned "active" location rows.
     */
    protected static function booted(): void
    {
        self::deleting(function (Business $business): void {
            if ($business->isForceDeleting()) {
                return;
            }

            $business->locations()->delete();
        });

        self::restoring(function (Business $business): void {
            $business->locations()->onlyTrashed()->restore();
        });
    }
}
