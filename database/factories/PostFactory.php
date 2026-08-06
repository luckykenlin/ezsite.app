<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PostCtaAction;
use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
final class PostFactory extends Factory
{
    protected $model = Post::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'title' => fake()->sentence(4),
            'excerpt' => fake()->sentence(12),
            'body' => fake()->paragraph()."\n\n".fake()->paragraph(),
            'kind' => PostKind::Update,
            'status' => PostStatus::Draft,
        ];
    }

    /**
     * Explicitly unpublished. Already the default; named so a test that is ABOUT
     * draftness reads as though it chose one.
     */
    public function draft(): self
    {
        return $this->state(fn (): array => [
            'status' => PostStatus::Draft,
            'published_at' => null,
        ]);
    }

    /**
     * Live on the public site. Draft is the default — matching the column default
     * and the composer — so a test that wants a visible update has to say so.
     */
    public function published(): self
    {
        return $this->state(fn (): array => [
            'status' => PostStatus::Published,
            'published_at' => now(),
        ]);
    }

    /**
     * A published offer inside its window, with the button and coupon an offer
     * carries.
     */
    public function offer(): self
    {
        return $this->published()->state(fn (): array => [
            'kind' => PostKind::Offer,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addWeek(),
            'cta_action' => PostCtaAction::Book,
            'cta_url' => '/contact',
            'offer_coupon_code' => 'SPRING10',
            'offer_terms' => 'One per customer. Not valid with other offers.',
        ]);
    }

    /**
     * A published closure notice inside its window.
     */
    public function hours(): self
    {
        return $this->published()->state(fn (): array => [
            'kind' => PostKind::Hours,
            'title' => 'Closed Monday for Lunar New Year',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(3),
        ]);
    }

    /**
     * Published, but its window has not opened yet — an offer put up on Friday to
     * run from Monday.
     */
    public function upcoming(): self
    {
        return $this->published()->state(fn (): array => [
            'kind' => PostKind::Offer,
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeeks(2),
        ]);
    }

    /**
     * A published update whose window has closed: still reachable at its own URL,
     * gone from every feed.
     */
    public function expired(): self
    {
        return $this->published()->state(fn (): array => [
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
        ]);
    }

    /**
     * Published, but long enough ago that the home-page block should hide itself
     * rather than advertise a business that may have closed.
     */
    public function stale(): self
    {
        return $this->published()->state(fn (): array => [
            'published_at' => now()->subYear(),
        ]);
    }
}
