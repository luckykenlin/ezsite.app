<?php

declare(strict_types=1);

use App\Http\Middleware\RememberLeadAttribution;
use App\Models\Post;
use App\Models\ReviewRequest;
use App\Site\ReservedSlugs;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

test('the reserved list covers every root route a page could hide behind', function (): void {
    // Derived from the constant the route is built from, so moving the prefix moves
    // the reservation with it.
    expect(ReservedSlugs::all())->toBe([Post::PATH_PREFIX, ReviewRequest::PATH_PREFIX, 'sitemap.xml', 'robots.txt']);
});

test('the list tracks every root path the tenant routes actually claim', function (): void {
    // The hand-maintained list's failure mode: somebody adds an explicit
    // tenant GET route above the fallback and forgets the reservation, and the
    // next operator page with that slug silently vanishes behind it. Derive
    // the claimed roots from the router so that PR fails here instead.
    // The PUBLIC tenant group is picked out by RememberLeadAttribution —
    // the one middleware unique to routes/tenant.php (panel and package
    // routes carry the tenancy middleware too, but never that one).
    $claimedRoots = collect(resolve(Router::class)->getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => in_array(RememberLeadAttribution::class, $route->gatherMiddleware(), true))
        ->reject(fn (Route $route): bool => $route->isFallback)
        ->filter(fn (Route $route): bool => in_array('GET', $route->methods(), true))
        ->map(fn (Route $route): string => explode('/', $route->uri())[0])
        // The `_`-prefixed internals are unreachable by any generated slug —
        // Str::slug() strips the underscore — and rejected by the typed-slug
        // rule along with everything on the list.
        ->reject(fn (string $root): bool => str_starts_with($root, '_') || $root === '/')
        ->unique()
        ->sort()
        ->values()
        ->all();

    expect(collect(ReservedSlugs::all())->sort()->values()->all())->toBe($claimedRoots);
});

test('a root slug that a route already owns is reserved', function (string $slug, bool $reserved): void {
    expect(ReservedSlugs::isReserved($slug))->toBe($reserved);
})->with([
    'the updates prefix' => ['updates', true],
    'with slashes around it' => ['/updates/', true],
    'in capitals' => ['Updates', true],
    'the sitemap' => ['sitemap.xml', true],
    'the review short link' => ['r', true],
    'an ordinary page' => ['about', false],
    'something merely similar' => ['updates-and-news', false],
]);

test('a child page may use the name, because no route claims that path', function (): void {
    // A child of /services slugged `updates` resolves to /services/updates, which
    // nothing else answers.
    expect(ReservedSlugs::isReserved('updates', parentId: 7))->toBeFalse()
        ->and(ReservedSlugs::avoid('updates', parentId: 7))->toBe('updates');
});

test('a generated slug steps around a reserved one visibly', function (): void {
    // Ugly and obvious beats a page nobody can reach.
    expect(ReservedSlugs::avoid('updates'))->toBe('updates-page')
        ->and(ReservedSlugs::avoid('about'))->toBe('about');
});

test('the list reads as a sentence for the operator who tripped over it', function (): void {
    expect(ReservedSlugs::describe())->toBe('updates, r, sitemap.xml and robots.txt');
});
