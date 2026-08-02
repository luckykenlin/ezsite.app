<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * The tenant site's sitemap: every published, indexable page and update of the
 * CURRENT tenant — RLS scopes both queries, so each tenant domain serves its own
 * list with no filtering in this class.
 *
 * Generated per request rather than written to a file: a tenant has a handful of
 * pages and a few dozen updates a year, and a stale file is worse than a cheap
 * query.
 */
final class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        // Two queries, one method. Per-request generation is still right — see the
        // note above — and a local business has a handful of pages and a few dozen
        // updates a year, not five thousand of either.
        $urls = $this->pages()
            ->merge($this->updates())
            ->implode("\n");

        return response(
            <<<XML
                <?xml version="1.0" encoding="UTF-8"?>
                <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                {$urls}
                </urlset>

                XML,
        )->header('Content-Type', 'application/xml');
    }

    /**
     * @return Collection<int, string>
     */
    private function pages(): Collection
    {
        return Page::query()
            ->where('status', PageStatus::Published)
            ->where('is_indexable', true)
            ->orderBy('id')
            ->get()
            ->map(fn (Page $page): string => $this->url($page->getUrl(), $page->updated_at->toAtomString()));
    }

    /**
     * Published, indexable updates plus the index that lists them.
     *
     * The index is listed only when there is something on it: a crawler pointed at
     * an empty page learns nothing, and an empty listed page is the kind of thin
     * content a sitemap should not advertise. An EXPIRED update stays in — its
     * page still answers 200 and still holds the copy somebody may have shared.
     *
     * @return Collection<int, string>
     */
    private function updates(): Collection
    {
        $posts = Post::query()
            ->published()
            ->where('is_indexable', true)
            ->get();

        if ($posts->isEmpty()) {
            return new Collection;
        }

        return $posts
            ->map(fn (Post $post): string => $this->url($post->getUrl(), $post->updated_at?->toAtomString() ?? ''))
            ->prepend($this->url('/'.Post::PATH_PREFIX, $posts->first()?->updated_at?->toAtomString() ?? ''))
            ->values();
    }

    private function url(string $path, string $lastModified): string
    {
        return sprintf(
            '    <url><loc>%s</loc><lastmod>%s</lastmod></url>',
            e(url($path)),
            e($lastModified),
        );
    }
}
