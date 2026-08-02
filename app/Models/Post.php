<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PostCtaAction;
use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Tenancy\RequiresTenantContext;
use Carbon\CarbonImmutable;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NunoMaduro\LaravelSluggable\Attributes\Sluggable;

/**
 * An update: one dated announcement with one photo, one paragraph or three, and
 * one button. Published at `/updates/{slug}` on the tenant's own site.
 *
 * Not a blog post, despite the table name. The shape follows Google Business
 * Profile's `LocalPost` — a kind, a window, a call to action from a closed set of
 * six — because that is also the shape a local business actually announces things
 * in ("two-day gel offer", "closed Monday", "new spring colours"), and because it
 * means the eventual Business Profile connector is a mapping rather than a
 * redesign.
 *
 * Columns rather than a blocks JSON, unlike {@see Page}: an update is one message
 * with one image, every field has a counterpart in an external payload, and
 * PHPStan can see all of them.
 *
 * RLS-only isolation, like every other tenant-owned model here: no
 * `BelongsToTenant`, no global scope, writes guarded by
 * {@see RequiresTenantContext}.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int|null $business_id
 * @property int|null $location_id
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property string|null $body
 * @property PostKind $kind
 * @property PostStatus $status
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property PostCtaAction|null $cta_action
 * @property string|null $cta_url
 * @property string|null $offer_coupon_code
 * @property string|null $offer_terms
 * @property int|null $cover_media_id
 * @property int|null $share_card_media_id
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property bool $is_indexable
 * @property string|null $author_name
 * @property string|null $idea_key
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static PostFactory factory($count = null, $state = [])
 */
#[Sluggable(from: 'title', scope: 'tenant_id')]
final class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    use RequiresTenantContext;

    /**
     * The public path prefix. Hard-coded rather than configurable: the closed
     * design system is the product's moat and a per-install prefix would be one
     * more axis to test across seven presets. Reserved in both slug paths so an
     * operator's page can never silently vanish behind it.
     */
    public const string PATH_PREFIX = 'updates';

    /**
     * Mirrors the two column defaults in PHP.
     *
     * The DB defaults are what keep the ten raw `Post::query()->create([...])`
     * call sites in the tenancy tests working; these are what stop the instance
     * they hand back from having a null `kind` until somebody re-fetches it.
     * Belt and braces on purpose — a caller reading `$post->kind->hint()` on a
     * freshly created row should not have to know which half filled it in.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'kind' => 'update',
        'status' => 'draft',
    ];

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
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * The public path, relative — matching {@see Page::getUrl()}, whose callers
     * all wrap it in `url()`. Relative on purpose: a worker has no request, so
     * `url()` there resolves against the CENTRAL domain, and anything needing an
     * absolute address has to say which host it means.
     */
    public function getUrl(): string
    {
        return sprintf('/%s/%s', self::PATH_PREFIX, $this->slug);
    }

    public function isPublished(): bool
    {
        return $this->status === PostStatus::Published;
    }

    /**
     * Where the update's button sends a visitor, or null when there is no
     * button.
     *
     * The phone for a CALL button comes from the business profile, never from
     * the update — factual data is referenced, never copied
     * (docs/business-data-model.md). Google rejects a CALL action carrying a
     * url at all, which is why the two arms are exclusive.
     */
    public function ctaHref(?Business $business): ?string
    {
        if ($this->cta_action === null) {
            return null;
        }

        return $this->cta_action->requiresUrl()
            ? ($this->cta_url ?? '/')
            : 'tel:'.($business->contact_phone ?? '');
    }

    /**
     * Whether the window has closed.
     *
     * Computed, never stored: with no scheduler there is nothing to stamp a
     * status, and a computed answer is right the instant it becomes right. An
     * expired update still serves a 200 — a link already live in a Facebook feed
     * must not die — but it drops out of the feed and the popup.
     */
    public function isExpired(): bool
    {
        return $this->ends_at !== null && $this->ends_at->isPast();
    }

    /**
     * Published, started, and not yet expired: what the home-page block, the
     * offer popup and the hours notice all mean by "current".
     */
    public function isCurrent(): bool
    {
        if (! $this->isPublished() || $this->isExpired()) {
            return false;
        }

        return $this->starts_at === null || ! $this->starts_at->isFuture();
    }

    /**
     * Published updates, newest first — the ordering every public surface wants,
     * and the one the `(tenant_id, status, published_at)` index serves.
     *
     * `published_at` descending with `id` as the tiebreak: two updates published
     * in the same second (a template seeding a demo site does exactly that) would
     * otherwise come back in an order Postgres is free to change between calls.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', PostStatus::Published)
            ->latest('published_at')
            ->orderByDesc('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => PostKind::class,
            'status' => PostStatus::class,
            'cta_action' => PostCtaAction::class,
            'published_at' => 'immutable_datetime',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'is_indexable' => 'boolean',
        ];
    }
}
