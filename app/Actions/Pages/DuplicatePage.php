<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Enums\PageStatus;
use App\Models\Page;

/**
 * Duplicates a whole page as a DRAFT sibling — title marked, slug deduped
 * within the same parent ("about-copy", "about-copy-2", …), blocks copied
 * verbatim. The page-level counterpart to {@see DuplicatePageBlock}, and the
 * action behind the assistant's own duplicate verb ({@see \App\Ai\Tools\DuplicatePage}).
 *
 * Must run in tenant context (RLS scopes the slug-collision lookups and the
 * write; the Page model's RequiresTenantContext guard enforces it).
 */
final readonly class DuplicatePage
{
    public function __construct(private UniquePageSlug $slugs) {}

    /**
     * @param  list<array{type: string, data: array<string, mixed>}>|null  $blocks  what to
     *                                                                              copy INSTEAD of the page's stored blocks. The chat path passes the
     *                                                                              editor's working draft: "make another page like this one" means the
     *                                                                              page on screen, and copying the saved version would silently omit
     *                                                                              everything the operator (or the assistant, moments earlier in the
     *                                                                              same turn) had not saved yet. Null keeps the stored blocks, which is
     *                                                                              what the canvas and editor buttons want.
     */
    public function handle(Page $page, ?array $blocks = null): Page
    {
        $base = ($page->slug === '/' ? 'home' : $page->slug).'-copy';

        return Page::query()->create([
            'tenant_id' => $page->tenant_id,
            'title' => $page->title.' (copy)',
            'slug' => $this->slugs->handle($base, $page->tenant_id, $page->parent_id),
            'layout' => $page->layout,
            'parent_id' => $page->parent_id,
            'blocks' => $blocks ?? $page->blocks ?? [],
            'status' => PageStatus::Draft,
        ]);
    }
}
