<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\RequiresTenantContext;
use Carbon\CarbonImmutable;
use Database\Factories\CatalogItemFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing the business sells: a service with a price and a duration for a
 * nail salon ("Gel manicure, $45, 45 min"), a dish for a restaurant.
 *
 * Structured columns rather than copy inside a page block, because this is
 * factual data (docs/business-data-model.md): the services page, the
 * schema.org `OfferCatalog`, the AI drafting the home page and a future
 * booking integration all read the same row, so a price change happens once.
 *
 * Price is integer cents and nullable — null is "ask for price", a real
 * answer for custom nail art. Add-ons live inline as JSON: they have no
 * identity of their own and are edited only as part of their service.
 *
 * RLS-only isolation, writes guarded by {@see RequiresTenantContext}.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $catalog_category_id
 * @property string $name
 * @property string|null $description
 * @property int|null $price_cents
 * @property bool $price_is_from
 * @property int|null $duration_minutes
 * @property list<array{name: string, price_cents: int}> $add_ons
 * @property list<string> $tags
 * @property int|null $media_id
 * @property bool $is_featured
 * @property int $sort
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static CatalogItemFactory factory($count = null, $state = [])
 */
final class CatalogItem extends Model
{
    /** @use HasFactory<CatalogItemFactory> */
    use HasFactory;

    use RequiresTenantContext;

    /**
     * Symbols for the currencies a price is written with a bare sign in.
     * Anything else is prefixed with its ISO code ("CHF 35").
     *
     * A lookup rather than `Number::currency()`, which needs ext-intl — not a
     * declared dependency, and Number throws rather than degrading without it
     * (the same trade-off noted on {@see \App\Templates\SiteTemplate::libraryCount()}).
     * A price on a services page must not 500 on a box without the extension.
     */
    private const array CURRENCY_SYMBOLS = [
        'USD' => '$',
        'CAD' => '$',
        'AUD' => '$',
        'NZD' => '$',
        'EUR' => '€',
        'GBP' => '£',
    ];

    /**
     * Mirrors the column defaults, so a freshly created instance reads `[]`
     * and `false` rather than null before it is re-fetched.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'price_is_from' => false,
        'add_ons' => '[]',
        'tags' => '[]',
        'is_featured' => false,
        'sort' => 0,
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<CatalogCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CatalogCategory::class, 'catalog_category_id');
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /**
     * The price as a visitor reads it — "$35", "$35.50", "from $65" — or null
     * when the item has no price ("ask for price" is the view's call to word).
     *
     * @param  string  $currency  ISO 4217 code, normally the business's `currency`
     */
    public function formattedPrice(string $currency): ?string
    {
        if ($this->price_cents === null) {
            return null;
        }

        $code = mb_strtoupper($currency);
        $decimals = $this->price_cents % 100 === 0 ? 0 : 2;
        $amount = (self::CURRENCY_SYMBOLS[$code] ?? $code.' ').number_format($this->price_cents / 100, $decimals);

        return $this->price_is_from ? __('from :price', ['price' => $amount]) : $amount;
    }

    /**
     * The duration as a visitor reads it — "45 min", "1 hr", "1 hr 15 min" —
     * or null when the item has none.
     */
    public function formattedDuration(): ?string
    {
        if ($this->duration_minutes === null) {
            return null;
        }

        $hours = intdiv($this->duration_minutes, 60);
        $minutes = $this->duration_minutes % 60;

        if ($hours === 0) {
            return sprintf('%d min', $minutes);
        }

        return $minutes === 0 ? sprintf('%d hr', $hours) : sprintf('%d hr %d min', $hours, $minutes);
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
            'price_cents' => 'integer',
            'price_is_from' => 'boolean',
            'duration_minutes' => 'integer',
            'add_ons' => 'array',
            'tags' => 'array',
            'is_featured' => 'boolean',
            'sort' => 'integer',
        ];
    }
}
