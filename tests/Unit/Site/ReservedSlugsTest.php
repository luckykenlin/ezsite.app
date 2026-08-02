<?php

declare(strict_types=1);

use App\Models\Post;
use App\Site\ReservedSlugs;

test('the reserved list covers every root route a page could hide behind', function (): void {
    // Derived from the constant the route is built from, so moving the prefix moves
    // the reservation with it.
    expect(ReservedSlugs::all())->toBe([Post::PATH_PREFIX, 'sitemap.xml', 'robots.txt']);
});

test('a root slug that a route already owns is reserved', function (string $slug, bool $reserved): void {
    expect(ReservedSlugs::isReserved($slug))->toBe($reserved);
})->with([
    'the updates prefix' => ['updates', true],
    'with slashes around it' => ['/updates/', true],
    'in capitals' => ['Updates', true],
    'the sitemap' => ['sitemap.xml', true],
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
    expect(ReservedSlugs::describe())->toBe('updates, sitemap.xml and robots.txt');
});
