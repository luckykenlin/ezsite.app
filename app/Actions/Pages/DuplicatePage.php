<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Enums\PageStatus;
use App\Models\Page;

/**
 * Duplicates a whole page as a DRAFT sibling — title marked, slug deduped
 * within the same parent ("about-copy", "about-copy-2", …), blocks copied
 * verbatim. The page-level counterpart to {@see DuplicatePageBlock}, and a
 * phase-2 AI verb ("make a roofing page modeled on Services").
 *
 * Must run in tenant context (RLS scopes the slug-collision lookups and the
 * write; the Page model's RequiresTenantContext guard enforces it).
 */
final readonly class DuplicatePage
{
    public function handle(Page $page): Page
    {
        return Page::query()->create([
            'tenant_id' => $page->tenant_id,
            'title' => $page->title.' (copy)',
            'slug' => $this->uniqueSlug($page),
            'layout' => $page->layout,
            'parent_id' => $page->parent_id,
            'blocks' => $page->blocks ?? [],
            'status' => PageStatus::Draft,
        ]);
    }

    private function uniqueSlug(Page $page): string
    {
        $base = ($page->slug === '/' ? 'home' : $page->slug).'-copy';
        $slug = $base;

        for ($suffix = 2; $this->slugTaken($page, $slug); $suffix++) {
            $slug = sprintf('%s-%d', $base, $suffix);
        }

        return $slug;
    }

    private function slugTaken(Page $page, string $slug): bool
    {
        return Page::query()
            ->where('tenant_id', $page->tenant_id)
            ->where('parent_id', $page->parent_id)
            ->where('slug', $slug)
            ->exists();
    }
}
