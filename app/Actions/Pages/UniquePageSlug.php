<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Models\Page;
use App\Site\ReservedSlugs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * The one place a page slug is made unique within its parent. Shared by
 * {@see DuplicatePage}, which feeds it an already-suffixed base ("about-copy"),
 * and {@see CreatePageFromName}, which feeds it a typed title — both have to
 * satisfy the same `unique(tenant_id, slug, parent_id)` index, and that index
 * is declared NULLS NOT DISTINCT, so root pages collide with each other too.
 *
 * Two rules the callers must not have to remember: `Str::slug()` returns an
 * empty string for input with nothing transliterable in it ("日本語"), and a
 * generated slug may never be "/" — the root slug is the home-page convention
 * and is only ever chosen deliberately.
 *
 * Must run in tenant context (RLS scopes the collision lookup).
 */
final readonly class UniquePageSlug
{
    private const string FALLBACK = 'page';

    /**
     * @param  string|null  $tenantId  redundant under RLS, which already scopes the
     *                                 lookup; passed by callers that hold a typed id
     */
    public function handle(string $base, ?string $tenantId = null, ?int $parentId = null): string
    {
        // Sidestep a slug a route already owns before looking for collisions:
        // `/updates` is a real route, so a page slugged `updates` would save fine
        // and then be unreachable. See App\Site\ReservedSlugs.
        $base = ReservedSlugs::avoid($this->normalise($base), $parentId);
        $slug = $base;

        for ($suffix = 2; $this->taken($slug, $tenantId, $parentId); $suffix++) {
            $slug = sprintf('%s-%d', $base, $suffix);
        }

        return $slug;
    }

    /**
     * The slug a base reduces to before any collision suffix — sanitised,
     * never empty, never the root slug.
     */
    private function normalise(string $base): string
    {
        $slug = Str::slug($base);

        return $slug === '' ? self::FALLBACK : $slug;
    }

    private function taken(string $slug, ?string $tenantId, ?int $parentId): bool
    {
        return Page::query()
            ->when($tenantId !== null, fn (Builder $query): Builder => $query->where('tenant_id', $tenantId))
            ->where('parent_id', $parentId)
            ->where('slug', $slug)
            ->exists();
    }
}
