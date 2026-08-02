<?php

declare(strict_types=1);

namespace App\Site;

use App\Enums\PostKind;
use App\Models\Post;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Everything the public site needs to know about a tenant's updates, in ONE
 * query per request.
 *
 * Three surfaces ask on a single page load — the home page's `updates` block,
 * the offer popup and the hours notice — and each of them wants a different
 * slice of the same answer. So this reads a window of the newest published
 * updates once and derives all three in PHP, rather than issuing three
 * similar selects. Scoped in the container beside {@see BindResolver},
 * {@see SiteChrome} and {@see MediaResolver}, for the reason those are:
 * memoization that lasts exactly one request, so a queue worker handling two
 * tenants cannot serve one of them the other's feed.
 *
 * The window is what makes one query enough, and it is a product rule as much as
 * a performance one: the popup advertises the LATEST offer, not the oldest one
 * still technically inside its date range. A salon publishes a few dozen updates
 * a year, so 24 covers every surface that exists.
 */
final class PostFeed
{
    /**
     * How many of the newest published updates to read — and, since a local
     * business does not need pagination, how many the index page lists.
     *
     * Comfortably above every caller's appetite: the block caps at 6 and a
     * page of two dozen dated cards is already more than anybody scrolls.
     */
    public const int WINDOW = 24;

    /** @var Collection<int, Post>|null */
    private ?Collection $recent = null;

    /**
     * The newest published updates, media already batched into the resolver.
     *
     * @return Collection<int, Post>
     */
    public function recent(): Collection
    {
        if ($this->recent instanceof Collection) {
            return $this->recent;
        }

        $this->recent = Post::query()->published()->limit(self::WINDOW)->get();

        // One media query for the whole render, the same batching the block loop
        // does — otherwise six cards on the home page are six selects.
        resolve(MediaResolver::class)->preload(
            array_values($this->recent->pluck('cover_media_id')->all()),
        );

        return $this->recent;
    }

    /**
     * The newest published updates that are still inside their window, newest
     * first — what a visitor should see.
     *
     * @return Collection<int, Post>
     */
    public function latest(int $limit, ?PostKind $kind = null): Collection
    {
        return $this->recent()
            ->filter(fn (Post $post): bool => $post->isCurrent())
            ->when(
                $kind instanceof PostKind,
                fn (Collection $posts): Collection => $posts->filter(
                    fn (Post $post): bool => $post->kind === $kind,
                ),
            )
            ->take($limit)
            ->values();
    }

    /**
     * The slice the `updates` block's variants render, from the block's own
     * STORED (and therefore untrusted) settings: an unreadable kind filter
     * means "everything" rather than an empty section, a nonsense count still
     * shows at least one, and a stale feed shows nothing at all — see
     * {@see isFresh()} for why an old strip is worse than none.
     *
     * @return Collection<int, Post>
     */
    public function forBlock(mixed $count, mixed $kindFilter): Collection
    {
        if (! $this->isFresh()) {
            return new Collection;
        }

        return $this->latest(
            max(1, is_numeric($count) ? (int) $count : 1),
            PostKind::tryFrom(is_string($kindFilter) ? $kindFilter : ''),
        );
    }

    /**
     * The offer the site should be advertising right now, if any.
     */
    public function currentOffer(): ?Post
    {
        return $this->latest(1, PostKind::Offer)->first();
    }

    /**
     * The closure or hours change the site should be announcing right now.
     */
    public function currentHoursNotice(): ?Post
    {
        return $this->latest(1, PostKind::Hours)->first();
    }

    /**
     * When the site last published anything.
     *
     * Not filtered by window: a finished offer still proves the site is tended.
     * This is what the staleness guard reads — a "Latest updates" strip whose
     * freshest item is eight months old tells a visitor comparing three salons
     * that this one may have closed, which makes the site convert worse than not
     * having the section at all.
     */
    public function lastPublishedAt(): ?CarbonImmutable
    {
        return $this->recent()->first()?->published_at;
    }

    /**
     * Whether a dated section may be shown at all: something published, and
     * published recently enough to be reassuring.
     */
    public function isFresh(): bool
    {
        $lastPublishedAt = $this->lastPublishedAt();

        if (! $lastPublishedAt instanceof CarbonImmutable) {
            return false;
        }

        return $lastPublishedAt->isAfter(now()->subDays(config()->integer('updates.stale_after_days')));
    }
}
