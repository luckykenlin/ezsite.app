<?php

declare(strict_types=1);

namespace App\Site;

use App\Models\Post;
use Illuminate\Support\Arr;

/**
 * The root-level paths a tenant page may not take, because a real route already
 * answers there.
 *
 * `routes/tenant.php` registers the Fabricator catch-all with `->fallback()`, so
 * Laravel places it last and every explicit route wins — which means a page
 * slugged `updates` does not conflict with `/updates`, it simply becomes
 * unreachable. Silently. The operator sees a saved page, a correct-looking URL in
 * the panel, and somebody else's content when they click it.
 *
 * Only ROOT slugs are reserved: a child of `/services` slugged `updates` resolves
 * to `/services/updates`, which no route claims.
 *
 * Guarded in BOTH slug paths, which is the part that is easy to get wrong:
 * {@see \App\Actions\Pages\UniquePageSlug} covers the generated ones (duplicate,
 * create-from-name), and the free-text field in
 * {@see \App\Filament\Tenant\Resources\PageResource\Actions\PageIdentityFields}
 * covers the ones an operator types — which is the path that actually happens.
 */
final readonly class ReservedSlugs
{
    /**
     * Every root path a route already owns.
     *
     * The internal `_`-prefixed routes are absent deliberately: `Str::slug()`
     * strips a leading underscore, so no generated slug can reach them, and the
     * typed-slug rule below rejects them along with everything else on this list.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            Post::PATH_PREFIX,
            'sitemap.xml',
            'robots.txt',
        ];
    }

    /**
     * Whether a slug at this level would be shadowed by a route.
     *
     * @param  int|null  $parentId  null for a root page, which is the only level
     *                              where a slug competes with a route
     */
    public static function isReserved(string $slug, ?int $parentId = null): bool
    {
        if ($parentId !== null) {
            return false;
        }

        return in_array(mb_strtolower(mb_trim($slug, '/')), self::all(), true);
    }

    /**
     * A slug the caller may safely use, suffixed if the one it wanted is taken by
     * a route. `updates` becomes `updates-page`, which is ugly and visible — far
     * better than a page nobody can reach.
     */
    public static function avoid(string $slug, ?int $parentId = null): string
    {
        return self::isReserved($slug, $parentId) ? $slug.'-page' : $slug;
    }

    /**
     * The list as a sentence, for the validation message an operator reads.
     */
    public static function describe(): string
    {
        return Arr::join(self::all(), ', ', ' and ');
    }
}
