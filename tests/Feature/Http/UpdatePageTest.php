<?php

declare(strict_types=1);

use App\Enums\PostKind;
use App\Models\Business;
use App\Models\Post;
use App\Models\Tenant;

/*
 * The public /updates surface: the permanent address that makes this feature worth
 * anything. It is the link an owner puts in an Instagram bio, what a Facebook
 * share resolves to, and what a Google Business Profile button will eventually
 * point at — so what matters here is that it exists, that it looks like the rest
 * of their site, and that a link to it never dies.
 */

function tenantWithUpdates(string $subdomain = 'acme'): Tenant
{
    $tenant = Tenant::factory()->withDomain($subdomain)->create();

    test()->runInTenant($tenant, fn (): Business => Business::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Jade Nails',
        'contact_phone' => '+1 555 0100',
    ]));

    // A published home page, so the site counts as LIVE. Without one, a tenant
    // with nothing published answers every miss with the coming-soon page at 200
    // rather than a 404 (see bootstrap/app.php) — correct behaviour, and it would
    // quietly make every 404 assertion below meaningless.
    test()->createTenantPage($tenant, [['type' => 'heading', 'data' => ['content' => 'Welcome']]], slug: '/');

    return $tenant;
}

function updateUrl(string $slug = ''): string
{
    return sprintf('http://acme.%s/updates%s', test()->centralDomain(), $slug === '' ? '' : '/'.$slug);
}

it('serves a published update inside the site own chrome and theme', function (): void {
    $tenant = tenantWithUpdates();

    $this->runInTenant($tenant, fn (): Post => Post::factory()->published()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Spring gel sets are here',
        'slug' => 'spring-gel-sets',
        'excerpt' => 'Six new colours, in the chair from Tuesday.',
        'body' => "First paragraph.\n\nSecond paragraph.",
    ]));

    $this->get(updateUrl('spring-gel-sets'))
        ->assertOk()
        ->assertSee('Spring gel sets are here')
        ->assertSee('Six new colours, in the chair from Tuesday.')
        ->assertSee('First paragraph.')
        ->assertSee('Second paragraph.')
        // The same document shell as a page: the tenant's theme variables and
        // their header. A visitor must not be able to tell this is not a page.
        ->assertSee('data-site-theme', false)
        ->assertSee('Jade Nails');
});

it('emits Article and BreadcrumbList structured data', function (): void {
    // The two schemas ralphjsmit/laravel-seo has always shipped and this app had
    // never used. An update is the one thing on a small-business site with a real
    // claim to either.
    $tenant = tenantWithUpdates();

    $this->runInTenant($tenant, fn (): Post => Post::factory()->published()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Spring gel sets',
        'slug' => 'spring-gel-sets',
        'author_name' => 'Mei, owner',
    ]));

    $response = $this->get(updateUrl('spring-gel-sets'))->assertOk();

    expect($response->content())
        ->toContain('"@type":"Article"')
        ->toContain('"@type":"BreadcrumbList"')
        ->toContain('Mei, owner')
        // Breadcrumbs read Home → Updates → this update.
        ->toContain('Updates');
});

it('emits an Offer node when the update is an offer with real dates', function (): void {
    $tenant = tenantWithUpdates();

    $this->runInTenant($tenant, fn (): Post => Post::factory()->offer()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Two days of ten percent off',
        'slug' => 'ten-percent',
    ]));

    $response = $this->get(updateUrl('ten-percent'))->assertOk();

    expect($response->content())
        ->toContain('"@type":"Offer"')
        ->toContain('availabilityStarts')
        ->toContain('availabilityEnds');
});

it('emits an Event node for an event, and none for one with no dates', function (): void {
    // Asserted through the rendered page rather than the action: SchemaCollection
    // holds builder closures and only becomes JSON-LD at render time. An event with
    // a real date range is the one genuinely rich-result-eligible thing here; an
    // event with no dates is an announcement, and an incomplete node is worse than
    // none because a validator flags it.
    $tenant = tenantWithUpdates();

    $this->runInTenant($tenant, function () use ($tenant): void {
        Post::factory()->published()->create([
            'tenant_id' => $tenant->id,
            'kind' => PostKind::Event,
            'title' => 'Open evening',
            'slug' => 'open-evening',
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(3),
        ]);
    });

    $response = $this->get(updateUrl('open-evening'))->assertOk();

    expect($response->content())
        ->toContain('"@type":"Event"')
        ->toContain('startDate')
        ->toContain('endDate')
        // Referenced from the business, never copied into the update.
        ->toContain('"organizer"')
        ->toContain('Jade Nails');
});

it('emits no dated node for an event with no dates', function (): void {
    $tenant = tenantWithUpdates();

    $this->runInTenant($tenant, fn (): Post => Post::factory()->published()->create([
        'tenant_id' => $tenant->id,
        'kind' => PostKind::Event,
        'slug' => 'someday',
        'starts_at' => null,
        'ends_at' => null,
    ]));

    $response = $this->get(updateUrl('someday'))->assertOk();

    expect($response->content())
        ->not->toContain('"@type":"Event"')
        // Article and breadcrumbs stay unconditional.
        ->toContain('"@type":"Article"');
});

it('shows the coupon code and the button on a running offer', function (): void {
    $tenant = tenantWithUpdates();

    $this->runInTenant($tenant, fn (): Post => Post::factory()->offer()->create([
        'tenant_id' => $tenant->id,
        'slug' => 'ten-percent',
    ]));

    $this->get(updateUrl('ten-percent'))
        ->assertOk()
        ->assertSee('SPRING10')
        ->assertSee('One per customer. Not valid with other offers.')
        ->assertSee('Book');
});

it('keeps an expired offer reachable but stops advertising it', function (): void {
    // The whole reason an expired update returns 200: a link already live in
    // somebody's Facebook feed must not start 404ing three weeks later. What
    // changes is the call to action, which becomes a plain honest sentence.
    $tenant = tenantWithUpdates();

    $this->runInTenant($tenant, fn (): Post => Post::factory()->offer()->expired()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Last month\'s deal',
        'slug' => 'last-month',
    ]));

    $this->get(updateUrl('last-month'))
        ->assertOk()
        ->assertSee('Last month')
        ->assertSee('ended on')
        ->assertDontSee('Book');
});

it('hides a draft update from the public site', function (): void {
    $tenant = tenantWithUpdates();

    $this->runInTenant($tenant, fn (): Post => Post::factory()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Not ready yet',
        'slug' => 'not-ready',
    ]));

    $this->get(updateUrl('not-ready'))
        ->assertNotFound()
        ->assertDontSee('Not ready yet');
});

it('cannot serve another tenant update', function (): void {
    // Binding on {post:slug} is RLS-scoped, so this needs no filtering in the
    // controller at all — the same property the sitemap relies on.
    $neighbour = Tenant::factory()->withDomain('globex')->create();
    $this->runInTenant($neighbour, fn (): Post => Post::factory()->published()->create([
        'tenant_id' => $neighbour->id,
        'title' => 'Somebody else news',
        'slug' => 'private-news',
    ]));

    tenantWithUpdates();

    $this->get(updateUrl('private-news'))
        ->assertNotFound()
        ->assertDontSee('Somebody else news');
});

it('respects the indexable switch', function (): void {
    $tenant = tenantWithUpdates();

    $this->runInTenant($tenant, fn (): Post => Post::factory()->published()->create([
        'tenant_id' => $tenant->id,
        'slug' => 'quiet-one',
        'is_indexable' => false,
    ]));

    $this->get(updateUrl('quiet-one'))
        ->assertOk()
        ->assertSee('noindex, nofollow', false);
});

it('says so plainly when there is nothing to list', function (): void {
    // 200, not 404: the site's own chrome links here, and a 404 on a page the site
    // points at reads as a broken website.
    tenantWithUpdates();

    $this->get(updateUrl())
        ->assertOk()
        ->assertSee('Nothing here just yet');
});

it('lists the updates newest first', function (): void {
    // One render per test: PostFeed is a scoped binding and nothing flushes scoped
    // instances between two $this->get() calls, so a second render would replay
    // the first one's memoized feed.
    $tenant = tenantWithUpdates();

    $this->runInTenant($tenant, function () use ($tenant): void {
        Post::factory()->published()->create(['tenant_id' => $tenant->id, 'title' => 'Older news', 'published_at' => now()->subWeek()]);
        Post::factory()->published()->create(['tenant_id' => $tenant->id, 'title' => 'Newest news']);
    });

    $response = $this->get(updateUrl())->assertOk()->assertSee('Older news')->assertSee('Newest news');

    expect(mb_strpos($response->content(), 'Newest news'))
        ->toBeLessThan(mb_strpos($response->content(), 'Older news'));
});

it('leaves an enquiry from an update out of the pages id space', function (): void {
    // The bug this guards: `posts` and `pages` are separate id sequences, so a
    // page_id borrowed from an update would silently attribute the lead to an
    // unrelated page — in the exact table the whole ROI story reads from. The
    // popup on an update page emits no page_id at all.
    $tenant = tenantWithUpdates();

    $this->runInTenant($tenant, function () use ($tenant): void {
        resolve(App\Actions\SaveSiteCapture::class)->handle(
            ['enabled' => true, 'heading' => 'Get 10% off', 'fields' => 'email'],
            [],
        );

        Post::factory()->published()->create(['tenant_id' => $tenant->id, 'slug' => 'spring']);
    });

    $this->get(updateUrl('spring'))
        ->assertOk()
        ->assertSee('Get 10% off')
        ->assertDontSee('name="page_id"', false);
});

it('lists published updates and their index in the sitemap', function (): void {
    $tenant = tenantWithUpdates();

    $this->runInTenant($tenant, function () use ($tenant): void {
        Post::factory()->published()->create(['tenant_id' => $tenant->id, 'slug' => 'listed']);
        Post::factory()->published()->create(['tenant_id' => $tenant->id, 'slug' => 'hidden', 'is_indexable' => false]);
        Post::factory()->create(['tenant_id' => $tenant->id, 'slug' => 'draft']);
    });

    $this->get(sprintf('http://acme.%s/sitemap.xml', $this->centralDomain()))
        ->assertOk()
        ->assertSee('/updates</loc>', false)
        ->assertSee('/updates/listed</loc>', false)
        ->assertDontSee('/updates/hidden', false)
        ->assertDontSee('/updates/draft', false);
});

it('keeps the updates index out of the sitemap when nothing is published', function (): void {
    // An empty listed page is thin content a sitemap should not advertise.
    tenantWithUpdates();

    $this->get(sprintf('http://acme.%s/sitemap.xml', $this->centralDomain()))
        ->assertOk()
        ->assertDontSee('/updates', false);
});

it('shows an hours notice across the site while it is running', function (): void {
    // "Are you open?" is the query that loses a local business the most
    // customers, and answering it needs no channel and no third party.
    $tenant = tenantWithUpdates();

    $this->runInTenant($tenant, fn (): Post => Post::factory()->hours()->create([
        'tenant_id' => $tenant->id,
        'slug' => 'lunar-new-year',
        'excerpt' => 'Back open Tuesday at 10am.',
    ]));

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Closed Monday for Lunar New Year')
        ->assertSee('Back open Tuesday at 10am.');
});

it('drops the hours notice the moment its window closes', function (): void {
    $tenant = tenantWithUpdates();

    $this->runInTenant($tenant, fn (): Post => Post::factory()->hours()->create([
        'tenant_id' => $tenant->id,
        'slug' => 'lunar-new-year',
        'starts_at' => now()->subWeeks(2),
        'ends_at' => now()->subDay(),
    ]));

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertDontSee('Closed Monday for Lunar New Year');
});

it('never lets a kind other than hours reach the notice bar', function (): void {
    $tenant = tenantWithUpdates();

    $this->runInTenant($tenant, fn (): Post => Post::factory()->offer()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Ten percent off',
        'slug' => 'ten-percent',
    ]));

    $response = $this->get(sprintf('http://acme.%s/', $this->centralDomain()))->assertOk();

    expect($response->content())->not->toContain('site-notice-bar');
    expect(PostKind::Hours->requiresDateRange())->toBeFalse();
});

it('lets the popup follow the current offer when the operator asks it to', function (): void {
    // Read-time derived, never written into site_settings: the offer appears when
    // its window opens and is gone the moment it closes, with nothing to switch off.
    // The house rule this follows is the documented one — factual data is
    // referenced, never copied.
    $tenant = tenantWithUpdates();

    $this->runInTenant($tenant, function () use ($tenant): void {
        resolve(App\Actions\SaveSiteCapture::class)->handle(
            [
                'enabled' => true,
                'follow_offer' => true,
                'heading' => 'Join our list',
                'offer' => 'Occasional news.',
                'fields' => 'email',
            ],
            [],
        );

        Post::factory()->offer()->create([
            'tenant_id' => $tenant->id,
            'title' => 'Two days of ten percent off',
            'excerpt' => 'Book any gel set before Sunday.',
            'slug' => 'ten-percent',
        ]);
    });

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Two days of ten percent off')
        ->assertSee('Book any gel set before Sunday.')
        // The coupon is worth more than the prose here — it is the reason a visitor
        // hands over an email address.
        ->assertSee('Use code SPRING10.')
        ->assertSee('One per customer. Not valid with other offers.')
        ->assertDontSee('Join our list');
});

it('gives the popup its own wording back the moment the offer ends', function (): void {
    $tenant = tenantWithUpdates();

    $this->runInTenant($tenant, function () use ($tenant): void {
        resolve(App\Actions\SaveSiteCapture::class)->handle(
            ['enabled' => true, 'follow_offer' => true, 'heading' => 'Join our list', 'fields' => 'email'],
            [],
        );

        Post::factory()->offer()->expired()->create([
            'tenant_id' => $tenant->id,
            'title' => 'Last month deal',
            'slug' => 'last-month',
        ]);
    });

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Join our list')
        ->assertDontSee('Last month deal');
});

it('leaves a configured popup alone unless the operator opted in', function (): void {
    // Silently rewriting a popup somebody configured is a surprise, and a salon may
    // want a standing "book a consultation" popup that outlives any one deal.
    $tenant = tenantWithUpdates();

    $this->runInTenant($tenant, function () use ($tenant): void {
        resolve(App\Actions\SaveSiteCapture::class)->handle(
            ['enabled' => true, 'heading' => 'Join our list', 'fields' => 'email'],
            [],
        );

        Post::factory()->offer()->create([
            'tenant_id' => $tenant->id,
            'title' => 'Two days of ten percent off',
            'slug' => 'ten-percent',
        ]);
    });

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Join our list')
        ->assertDontSee('Two days of ten percent off');
});

it('shares with the generated card rather than the raw photograph', function (): void {
    // Growth mechanism #2, and the one only a website builder can offer: we own the
    // head of every tenant page, so a free sharer.php or x.com/intent link renders a
    // card built from OUR OpenGraph tags — correctly cropped, and still correct
    // after the sharer rewrites their own words.
    $tenant = tenantWithUpdates();

    $this->runInTenant($tenant, function () use ($tenant): void {
        $bytes = (string) Intervention\Image\ImageManager::gd()->create(2000, 1200)->fill('b91c1c')->toJpeg();
        $disk = config()->string('curator.default_disk');
        Illuminate\Support\Facades\Storage::disk($disk)->put('covers/cover.jpg', $bytes);

        $cover = App\Models\Media::factory()->create([
            'tenant_id' => $tenant->id,
            'disk' => $disk,
            'path' => 'covers/cover.jpg',
            'ext' => 'jpg',
            'type' => 'image/jpeg',
        ]);

        $post = Post::factory()->create([
            'tenant_id' => $tenant->id,
            'title' => 'Spring gel sets',
            'slug' => 'spring-gel-sets',
            'cover_media_id' => $cover->id,
        ]);

        resolve(App\Actions\Posts\PublishPost::class)->handle($post);
    });

    $this->get(updateUrl('spring-gel-sets'))
        ->assertOk()
        ->assertSee('share-cards/', false)
        ->assertSee('og:image', false);
});
