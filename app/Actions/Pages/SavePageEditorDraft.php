<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Models\Page;

/**
 * Persist (or clear) the page editor's unsaved working state.
 *
 * The editor rebuilt everything from `pages.blocks` and `site_settings` on every
 * mount, so a refresh — or a crash, or a 419 after the session expired — threw
 * away the whole draft. This is its durable home.
 *
 * NOT the cache, despite `CACHE_STORE=database` making that technically durable:
 * `Cache::flush()` on that store issues `DELETE FROM cache` ignoring the tenant
 * prefix, and `php artisan optimize:clear` calls it. Once the editor promises "your
 * work survives a refresh", backing that promise with a store whose defining
 * contract is "contents may vanish at any time" is a category error — every deploy
 * runbook would silently destroy every operator's unsaved work.
 *
 * A column on `pages` rather than its own table: it inherits the RLS policy
 * generated from `pages.tenant_id`, cascade-deletes with the page, and needs no
 * model, factory or policy of its own. The consequence is that the draft is per
 * PAGE, not per user — a teammate opening the page adopts the unsaved work rather
 * than starting clean, which matches the existing decision that a page's chat
 * thread is shared, and is no worse than today's last-writer-wins on Save.
 */
final readonly class SavePageEditorDraft
{
    /**
     * DO NOT "simplify" this to `$page->update()`. Two reasons, both load-bearing:
     *
     * 1. Fabricator registers `PageRoutesObserver` on the page model, and its
     *    `updated()` calls `PageRoutesService::updateUrlsOf()` — which reads the
     *    whole URI→ID mapping, walks every descendant page, and writes the mapping
     *    back. This action runs on every debounced keystroke, so model events would
     *    thrash that several times a second.
     * 2. `updated_at` must not move. The draft is not page identity; a page whose
     *    draft is being typed into has not been modified.
     *
     * Going through the query builder also skips `RequiresTenantContext`, which is
     * safe by construction here: `whereKey()` on a page we already hold cannot
     * reach another tenant's row, and both callers are inside an authenticated
     * tenant request.
     *
     * `Page::query()` rather than `$page->newQuery()`, deliberately: an instance
     * loaded inside tenancy remembers the `tenant` connection that
     * `PostgresRLSBootstrapper` installs, and that connection is purged when
     * tenancy ends — so `$page->newQuery()` throws for any caller holding a page
     * across that boundary. A fresh builder resolves the default connection, which
     * is the RLS-scoped one inside tenancy and the plain one outside it.
     *
     * @param  array{blocks: list<array{key: string, type: string, data: array<string, mixed>}>, chrome: array<string, array{type: string, data: array<string, mixed>}|null>, chrome_dirty: bool, selected_block_key: string|null, inspector: array<string, mixed>|null, sample_hint_shown: bool, chat_edit_awaiting_save: bool, chat_turn: array{token: string, started_at: int}|null, design: array<string, string|null>|null}|null  $draft  null clears the draft
     */
    public function handle(Page $page, ?array $draft): void
    {
        // toBase(), because Eloquent's builder helpfully stamps `updated_at` on
        // every update() and that is precisely what must not happen here.
        $query = Page::query()->whereKey($page->getKey())->toBase();

        if ($draft === null) {
            // Touch no rows when there is nothing to clear. A clean editing session
            // calls this once on mount and never again, so this is the common case.
            $query->whereNotNull('draft')->update([
                'draft' => null,
                'draft_updated_at' => null,
            ]);

            return;
        }

        $query->update([
            'draft' => json_encode($draft, JSON_THROW_ON_ERROR),
            'draft_updated_at' => now(),
        ]);
    }
}
