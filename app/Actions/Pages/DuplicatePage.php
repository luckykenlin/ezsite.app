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
    public function __construct(private UniquePageSlug $slugs) {}

    public function handle(Page $page): Page
    {
        $base = ($page->slug === '/' ? 'home' : $page->slug).'-copy';

        return Page::query()->create([
            'tenant_id' => $page->tenant_id,
            'title' => $page->title.' (copy)',
            'slug' => $this->slugs->handle($base, $page->tenant_id, $page->parent_id),
            'layout' => $page->layout,
            'parent_id' => $page->parent_id,
            'blocks' => $page->blocks ?? [],
            'status' => PageStatus::Draft,
        ]);
    }
}
