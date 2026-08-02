<?php

declare(strict_types=1);

use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Models\Location;
use App\Models\Post;
use App\Models\Tenant;
use Illuminate\Database\QueryException;

test('tenant relation returns the owning tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $post = $this->runInTenant($tenant, fn (): Post => Post::factory()->create(['tenant_id' => $tenant->id]));

    expect($post->tenant->is($tenant))->toBeTrue();
});

test('business relation returns the owning business', function (): void {
    // Asserted INSIDE the tenant context: RunInTenant purges the `tenant`
    // connection on the way out, so a model carried back cannot resolve its own
    // relations any more (documented on RunInTenant itself).
    $tenant = Tenant::factory()->create();
    $business = $this->createTenantBusiness($tenant, locations: 0);

    $this->runInTenant($tenant, function () use ($tenant, $business): void {
        $post = Post::factory()->create([
            'tenant_id' => $tenant->id,
            'business_id' => $business->id,
        ]);

        expect($post->business->is($business))->toBeTrue();
    });
});

test('location relation returns the branch an update belongs to', function (): void {
    // A closure or an offer usually belongs to one branch, which is also the unit
    // a Google Business Profile post targets.
    $tenant = Tenant::factory()->create();
    $business = $this->createTenantBusiness($tenant, locations: 0);

    $this->runInTenant($tenant, function () use ($tenant, $business): void {
        $location = Location::factory()->create([
            'tenant_id' => $tenant->id,
            'business_id' => $business->id,
            'is_primary' => true,
        ]);

        $post = Post::factory()->create([
            'tenant_id' => $tenant->id,
            'business_id' => $business->id,
            'location_id' => $location->id,
        ]);

        expect($post->location->is($location))->toBeTrue();
    });
});

test('a slug is generated from the title without being asked for one', function (): void {
    // Load-bearing: ten call sites across the tenancy tests and tests/Fixtures
    // create a Post with nothing but tenant_id and title.
    $tenant = Tenant::factory()->create();

    $post = $this->runInTenant($tenant, fn (): Post => Post::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Spring gel sets are here',
    ]));

    // The instance and the stored row must agree: the column defaults are what
    // keep those raw call sites working, and $attributes is what stops the
    // instance they hand back from reading null until somebody re-fetches.
    $stored = Post::query()->findOrFail($post->getKey());

    expect($post->slug)->toBe('spring-gel-sets-are-here')
        ->and($post->kind)->toBe(PostKind::Update)
        ->and($post->status)->toBe(PostStatus::Draft)
        ->and($stored->kind)->toBe(PostKind::Update)
        ->and($stored->status)->toBe(PostStatus::Draft);
});

test('two updates with the same title get distinct slugs', function (): void {
    $tenant = Tenant::factory()->create();

    $slugs = $this->runInTenant($tenant, fn (): array => [
        Post::factory()->create(['tenant_id' => $tenant->id, 'title' => 'Closed Monday'])->slug,
        Post::factory()->create(['tenant_id' => $tenant->id, 'title' => 'Closed Monday'])->slug,
    ]);

    expect($slugs[0])->toBe('closed-monday')
        ->and($slugs[1])->not->toBe($slugs[0]);
});

test('the public url is relative, the way a page url is', function (): void {
    // Relative on purpose: a worker has no request, so url() there resolves
    // against the CENTRAL domain. Anything needing an absolute address has to say
    // which host it means.
    $tenant = Tenant::factory()->create();
    $post = $this->runInTenant($tenant, fn (): Post => Post::factory()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Spring gel sets',
    ]));

    expect($post->getUrl())->toBe('/updates/spring-gel-sets');
});

test('an update is current only while published and inside its window', function (
    string $state,
    bool $published,
    bool $expired,
    bool $current,
): void {
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function () use ($tenant, $state, $published, $expired, $current): void {
        $post = Post::factory()->{$state}()->create(['tenant_id' => $tenant->id]);

        expect($post->isPublished())->toBe($published)
            ->and($post->isExpired())->toBe($expired)
            ->and($post->isCurrent())->toBe($current);
    });
})->with([
    'a draft' => ['draft', false, false, false],
    'published with no window' => ['published', true, false, true],
    'a running offer' => ['offer', true, false, true],
    'a finished offer' => ['expired', true, true, false],
    'scheduled to start later' => ['upcoming', true, false, false],
]);

test('the body splits into paragraphs on blank lines', function (?string $body, array $paragraphs): void {
    // The view renders one escaped <p> per entry rather than nl2br over the whole
    // column, which keeps {!! !!} off a page built from tenant-authored text.
    $post = Post::factory()->make(['body' => $body]);

    expect($post->paragraphs())->toBe($paragraphs);
})->with([
    'nothing written' => [null, []],
    'one paragraph' => ['Just the one.', ['Just the one.']],
    'two paragraphs' => ["First.\n\nSecond.", ['First.', 'Second.']],
    'a single newline is not a break' => ["First\nstill first.", ["First\nstill first."]],
    'runs of blank lines collapse' => ["First.\n\n\n\nSecond.", ['First.', 'Second.']],
    'trailing whitespace is trimmed away' => ["  First.  \n\n  \n\nSecond.\n\n", ['First.', 'Second.']],
]);

test('the published scope returns only published updates, newest first', function (): void {
    $tenant = Tenant::factory()->create();

    $titles = $this->runInTenant($tenant, function () use ($tenant): array {
        Post::factory()->create(['tenant_id' => $tenant->id, 'title' => 'Still a draft']);
        Post::factory()->published()->create(['tenant_id' => $tenant->id, 'title' => 'Older', 'published_at' => now()->subWeek()]);
        Post::factory()->published()->create(['tenant_id' => $tenant->id, 'title' => 'Newer']);

        return Post::query()->published()->pluck('title')->all();
    });

    expect($titles)->toBe(['Newer', 'Older']);
});

test('the current scope drops finished and not-yet-started updates', function (): void {
    $tenant = Tenant::factory()->create();

    $titles = $this->runInTenant($tenant, function () use ($tenant): array {
        Post::factory()->offer()->create(['tenant_id' => $tenant->id, 'title' => 'Running']);
        Post::factory()->expired()->create(['tenant_id' => $tenant->id, 'title' => 'Finished']);
        Post::factory()->upcoming()->create(['tenant_id' => $tenant->id, 'title' => 'Not yet']);
        Post::factory()->create(['tenant_id' => $tenant->id, 'title' => 'Draft']);

        return Post::query()->current()->pluck('title')->all();
    });

    expect($titles)->toBe(['Running']);
});

test('a tenant cannot reuse a slug', function (): void {
    // The unique index leads with tenant_id so it doubles as the RLS predicate
    // index; two tenants may both have /updates/closed-monday.
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function () use ($tenant): void {
        Post::factory()->create(['tenant_id' => $tenant->id, 'slug' => 'closed-monday']);

        expect(fn (): Post => Post::factory()->create(['tenant_id' => $tenant->id, 'slug' => 'closed-monday']))
            ->toThrow(QueryException::class);
    });
});

test('two tenants can hold the same slug', function (): void {
    $first = Tenant::factory()->create();
    $second = Tenant::factory()->create();

    foreach ([$first, $second] as $tenant) {
        $this->runInTenant($tenant, fn (): Post => Post::factory()->create([
            'tenant_id' => $tenant->id,
            'slug' => 'closed-monday',
        ]));
    }

    expect(Post::query()->where('slug', 'closed-monday')->count())->toBe(2);
});

test('to array', function (): void {
    $tenant = Tenant::factory()->create();
    $post = $this->runInTenant($tenant, fn (): Post => Post::factory()->create(['tenant_id' => $tenant->id]));
    $post = Post::query()->findOrFail($post->getKey());

    expect(array_keys($post->toArray()))
        ->toBe([
            'id',
            'tenant_id',
            'business_id',
            'location_id',
            'title',
            'slug',
            'excerpt',
            'body',
            'kind',
            'status',
            'published_at',
            'starts_at',
            'ends_at',
            'cta_action',
            'cta_url',
            'offer_coupon_code',
            'offer_terms',
            'cover_media_id',
            'share_card_media_id',
            'seo_title',
            'seo_description',
            'is_indexable',
            'author_name',
            'idea_key',
            'created_at',
            'updated_at',
        ]);
});
