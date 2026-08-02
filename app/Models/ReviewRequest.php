<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReviewChannel;
use App\Enums\ReviewRequestStatus;
use App\Tenancy\RequiresTenantContext;
use Carbon\CarbonImmutable;
use Database\Factories\ReviewRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ask for a review: a tracked short link a customer can be handed, printed on
 * a receipt, or stuck on the counter as a QR code.
 *
 * The whole point is that it needs NOTHING. No API, no approval, no content from
 * the owner — and it moves the factor Google's own prominence documentation
 * actually names, which is review quantity, velocity and rating. Everything else
 * in the updates feature asks an owner to write something weekly; this asks them
 * to print one square.
 *
 * The shape is `docs/reviews-module.md`'s, so the rest of that module can grow into
 * it rather than around it.
 *
 * RLS-only isolation like every other tenant-owned model, writes guarded by
 * {@see RequiresTenantContext}.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int|null $business_id
 * @property int|null $location_id
 * @property ReviewChannel $channel
 * @property string|null $recipient
 * @property ReviewRequestStatus $status
 * @property string $token
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $clicked_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static ReviewRequestFactory factory($count = null, $state = [])
 */
final class ReviewRequest extends Model
{
    /** @use HasFactory<ReviewRequestFactory> */
    use HasFactory;

    use RequiresTenantContext;

    /**
     * The public path the short link lives at.
     *
     * Two characters, because this is printed on a receipt and read off a counter
     * card by someone holding a phone — every character is one more chance to
     * mistype. Reserved in both page-slug paths for the same reason `/updates` is.
     */
    public const string PATH_PREFIX = 'r';

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
     * The tracked path, relative — the same convention {@see Post::getUrl()} and
     * {@see Page::getUrl()} follow, so a caller has to say which host it means.
     */
    public function getUrl(): string
    {
        return sprintf('/%s/%s', self::PATH_PREFIX, $this->token);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => ReviewChannel::class,
            'status' => ReviewRequestStatus::class,
            'sent_at' => 'immutable_datetime',
            'clicked_at' => 'immutable_datetime',
        ];
    }
}
