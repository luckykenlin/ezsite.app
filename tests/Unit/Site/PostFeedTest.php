<?php

declare(strict_types=1);

use App\Enums\PostKind;
use App\Models\Media;
use App\Models\Post;
use App\Models\Tenant;
use App\Site\PostFeed;
use Illuminate\Support\Facades\DB;

/**
 * Ask the feed a question INSIDE the tenant's RLS context, from a clean container.
 *
 * Both halves matter. Scoped, so a stale instance would answer a later test's
 * question; and inside, because RunInTenant purges the `tenant` connection on the
 * way out — a feed carried back would run its lazy query on the BYPASSRLS central
 * connection and cheerfully return every tenant's updates.
 *
 * @param  Closure(PostFeed): void  $assert
 */
function withFeed(Tenant $tenant, Closure $assert): void
{
    test()->runInTenant($tenant, function () use ($assert): void {
        app()->forgetScopedInstances();

        $assert(resolve(PostFeed::class));
    });
}

it('answers every public surface from one query', function (): void {
    // The reason this class exists. Four surfaces ask on one page load — the block,
    // the nav entry, the offer popup and the hours notice — and asserting the
    // CONSEQUENCE is the only way to catch the binding degrading to bind(), since
    // expecting the same instance back would pass for that too.
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function () use ($tenant): void {
        Post::factory()->count(3)->published()->create(['tenant_id' => $tenant->id]);
        Post::factory()->offer()->create([
            'tenant_id' => $tenant->id,
            // One real cover, so the media batch below is exercised rather than
            // skipped — MediaResolver issues nothing when every id is null.
            'cover_media_id' => Media::factory()->create(['tenant_id' => $tenant->id])->id,
        ]);
        Post::factory()->hours()->create(['tenant_id' => $tenant->id]);
    });

    $this->runInTenant($tenant, function (): void {
        app()->forgetScopedInstances();
        $feed = resolve(PostFeed::class);

        DB::enableQueryLog();

        $feed->latest(3);
        $feed->currentOffer();
        $feed->currentHoursNotice();
        $feed->lastPublishedAt();
        $feed->isWorthLinking();
        $feed->isFresh();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // One read of the window, plus the one media batch it hands to the
        // resolver so six cards are not six selects.
        expect($queries)->toHaveCount(2);
    });
});

it('shows only current updates, newest first', function (): void {
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function () use ($tenant): void {
        Post::factory()->published()->create(['tenant_id' => $tenant->id, 'title' => 'Older', 'published_at' => now()->subWeek()]);
        Post::factory()->published()->create(['tenant_id' => $tenant->id, 'title' => 'Newer']);
        Post::factory()->expired()->create(['tenant_id' => $tenant->id, 'title' => 'Finished']);
        Post::factory()->upcoming()->create(['tenant_id' => $tenant->id, 'title' => 'Not yet']);
        Post::factory()->create(['tenant_id' => $tenant->id, 'title' => 'Draft']);
    });

    withFeed($tenant, fn (PostFeed $feed) => expect($feed->latest(10)->pluck('title')->all())->toBe(['Newer', 'Older']));
});

it('honours a limit and a kind filter', function (): void {
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function () use ($tenant): void {
        Post::factory()->count(4)->published()->create(['tenant_id' => $tenant->id]);
        Post::factory()->offer()->create(['tenant_id' => $tenant->id, 'title' => 'The offer']);
    });

    withFeed($tenant, fn (PostFeed $feed) => expect($feed->latest(2))->toHaveCount(2)
        ->and($feed->latest(10, PostKind::Offer)->pluck('title')->all())->toBe(['The offer']));
});

it('finds the current offer and the current closure notice', function (): void {
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function () use ($tenant): void {
        Post::factory()->offer()->create(['tenant_id' => $tenant->id, 'title' => 'Ten percent off']);
        Post::factory()->hours()->create(['tenant_id' => $tenant->id]);
    });

    withFeed($tenant, fn (PostFeed $feed) => expect($feed->currentOffer()?->title)->toBe('Ten percent off')
        ->and($feed->currentHoursNotice()?->kind)->toBe(PostKind::Hours));
});

it('advertises the LATEST offer, not the oldest one still technically running', function (): void {
    // A product rule as much as a windowing one: an offer put up in January that
    // happens to end in December must not outrank this week's.
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function () use ($tenant): void {
        Post::factory()->offer()->create([
            'tenant_id' => $tenant->id,
            'title' => 'January leftovers',
            'published_at' => now()->subMonths(2),
            'ends_at' => now()->addYear(),
        ]);
        Post::factory()->offer()->create(['tenant_id' => $tenant->id, 'title' => 'This week']);
    });

    withFeed($tenant, fn (PostFeed $feed) => expect($feed->currentOffer()?->title)->toBe('This week'));
});

it('finds nothing to offer or announce when there is nothing running', function (): void {
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, fn (): Post => Post::factory()->expired()->create(['tenant_id' => $tenant->id]));

    withFeed($tenant, fn (PostFeed $feed) => expect($feed->currentOffer())->toBeNull()
        ->and($feed->currentHoursNotice())->toBeNull());
});

it('waits for a second update before the nav is worth a link', function (int $count, bool $worthLinking): void {
    // A nav entry leading to a page with one item reads as an unfinished site,
    // which is the opposite of what the section is for.
    $tenant = Tenant::factory()->create();

    if ($count > 0) {
        $this->runInTenant($tenant, fn () => Post::factory()->count($count)->published()->create(['tenant_id' => $tenant->id]));
    }

    withFeed($tenant, fn (PostFeed $feed) => expect($feed->isWorthLinking())->toBe($worthLinking));
})->with([
    'nothing published' => [0, false],
    'one update' => [1, false],
    'two updates' => [2, true],
]);

it('reports a site as stale once its newest update is old', function (): void {
    // The staleness guard the block reads. A "Latest updates" strip whose freshest
    // item is eight months old tells a visitor comparing three salons that this one
    // may have closed — which makes the site convert worse than not having it.
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, fn (): Post => Post::factory()->stale()->create(['tenant_id' => $tenant->id]));

    withFeed($tenant, fn (PostFeed $feed) => expect($feed->isFresh())->toBeFalse()
        ->and($feed->lastPublishedAt())->not->toBeNull());
});

it('reports a site with nothing published as neither fresh nor linkable', function (): void {
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, fn (): Post => Post::factory()->create(['tenant_id' => $tenant->id]));

    withFeed($tenant, fn (PostFeed $feed) => expect($feed->isFresh())->toBeFalse()
        ->and($feed->lastPublishedAt())->toBeNull()
        ->and($feed->isWorthLinking())->toBeFalse());
});

it('counts a finished offer as tending the site even though it is not shown', function (): void {
    // lastPublishedAt() is deliberately not window-filtered: an offer that ran last
    // week still proves somebody is here.
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, fn (): Post => Post::factory()->expired()->create(['tenant_id' => $tenant->id]));

    withFeed($tenant, fn (PostFeed $feed) => expect($feed->isFresh())->toBeTrue()
        ->and($feed->latest(10))->toBeEmpty());
});

it('sees only the current tenant updates', function (): void {
    $neighbour = Tenant::factory()->create();
    $this->runInTenant($neighbour, fn () => Post::factory()->count(3)->published()->create(['tenant_id' => $neighbour->id]));

    $tenant = Tenant::factory()->create();

    withFeed($tenant, fn (PostFeed $feed) => expect($feed->latest(10))->toBeEmpty());
});
