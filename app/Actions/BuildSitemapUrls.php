<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\Post;

/**
 * Every URL the current tenant's sitemap should list: published, indexable
 * pages and updates, RLS-scoped, so each tenant domain gets its own list with
 * no filtering here.
 *
 * Deliberately NOT read through {@see \App\Site\PostFeed}: the feed windows
 * to the newest two dozen for the public surfaces, and it does not filter on
 * `is_indexable`. A sitemap wants the opposite slice — everything indexable,
 * however old. An EXPIRED update stays in: its page still answers 200 and
 * still holds the copy somebody may have shared.
 */
final readonly class BuildSitemapUrls
{
    /**
     * @return list<array{loc: string, lastmod: string|null}>
     */
    public function handle(): array
    {
        return [...$this->pages(), ...$this->updates()];
    }

    /**
     * @return list<array{loc: string, lastmod: string|null}>
     */
    private function pages(): array
    {
        return array_values(Page::query()
            ->where('status', PageStatus::Published)
            ->where('is_indexable', true)
            ->orderBy('id')
            ->get()
            ->map(fn (Page $page): array => [
                'loc' => url($page->getUrl()),
                'lastmod' => $page->updated_at->toAtomString(),
            ])
            ->all());
    }

    /**
     * Published, indexable updates plus the index that lists them.
     *
     * The index is listed only when there is something on it: a crawler
     * pointed at an empty page learns nothing, and an empty listed page is the
     * kind of thin content a sitemap should not advertise.
     *
     * @return list<array{loc: string, lastmod: string|null}>
     */
    private function updates(): array
    {
        $posts = Post::query()
            ->published()
            ->where('is_indexable', true)
            ->get();

        if ($posts->isEmpty()) {
            return [];
        }

        return array_values($posts
            ->map(fn (Post $post): array => [
                'loc' => url($post->getUrl()),
                'lastmod' => $post->updated_at?->toAtomString(),
            ])
            ->prepend([
                'loc' => url('/'.Post::PATH_PREFIX),
                'lastmod' => $posts->first()->updated_at?->toAtomString(),
            ])
            ->all());
    }
}
