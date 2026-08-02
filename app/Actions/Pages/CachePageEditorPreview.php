<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Enums\ChromeSlot;
use App\Models\Page;
use Illuminate\Support\Facades\Cache;

/**
 * Publishes the page editor's draft state to the cache, where the canvas
 * preview route ({@see \App\Http\Controllers\PageEditorPreviewController})
 * picks it up by token. The payload is plain arrays — deliberately not a
 * serialized Eloquent model, so it needs no `cache.serializable_classes`
 * allowlisting (unlike filament-peek's preview cache). The editor re-puts
 * under the same token on every draft mutation, which both refreshes the
 * TTL and makes the iframe reload pick up the new state.
 *
 * The panel and the preview route run under the same tenant, so the
 * CacheTenancyBootstrapper prefixes both sides identically — a token is
 * only resolvable inside the tenant that wrote it.
 */
final readonly class CachePageEditorPreview
{
    public static function key(string $token): string
    {
        return 'page-editor-preview:'.$token;
    }

    /**
     * @param  list<array{key: string, type: string, data: array<string, mixed>}>  $blocks
     * @param  array<string, array{type: string, data: array<string, mixed>}|null>|null  $chrome  header/footer draft entries; null = render live chrome
     * @param  array<string, string|null>|null  $designTokens  token-value draft overriding the saved theme; null = saved theme
     */
    public function handle(Page $page, array $blocks, string $token, ?array $chrome = null, ?array $designTokens = null): void
    {
        Cache::put(self::key($token), [
            'page' => [
                'id' => $page->id,
                'title' => $page->title,
                'layout' => $page->layout,
            ],
            'blocks' => array_map(
                static fn (array $block): array => ['type' => $block['type'], 'data' => $block['data']],
                $blocks,
            ),
            'keys' => array_column($blocks, 'key'),
            'chrome' => $chrome === null ? null : $this->renderReadyChrome($chrome),
            'design_tokens' => $designTokens,
        ], now()->addHours(2));
    }

    /**
     * Swap the STAGED parts of an existing preview entry — the chat worker's
     * write path, which lets the canvas repaint after every tool call while a
     * turn is still running: the blocks always, plus whatever style and chrome
     * the turn has staged so far, so a recolour paints mid-turn instead of
     * leaving the operator watching an unchanged canvas for ninety seconds.
     *
     * A null design or chrome preserves what the editor last pushed: the turn
     * has not staged one, and overwriting the inspector's own draft with the
     * saved state would repaint the canvas in a state the operator never had.
     * Chrome merges per SLOT for the same reason — a turn that edited the
     * header must not clobber a footer the operator is editing by hand. The
     * merge is skipped entirely when the entry carries no chrome array (a
     * caller with no canvas chrome), since a partial one would drop the other
     * slot's live render.
     *
     * False when no entry exists (never pushed, or expired) — the caller skips
     * its repaint signal, since there is nothing on screen to go stale.
     *
     * @param  list<array{key: string, type: string, data: array<string, mixed>}>  $blocks
     * @param  array<string, string|null>|null  $design
     * @param  array<string, array{type: string, data: array<string, mixed>}>|null  $chrome
     */
    public function replaceStaged(string $token, array $blocks, ?array $design = null, ?array $chrome = null): bool
    {
        $entry = Cache::get(self::key($token));

        if (! is_array($entry)) {
            return false;
        }

        $entry['blocks'] = array_map(
            static fn (array $block): array => ['type' => $block['type'], 'data' => $block['data']],
            $blocks,
        );
        $entry['keys'] = array_column($blocks, 'key');

        if ($design !== null) {
            $entry['design_tokens'] = $design;
        }

        if ($chrome !== null && is_array($entry['chrome'] ?? null)) {
            foreach (ChromeSlot::cases() as $slot) {
                $staged = $chrome[$slot->value] ?? null;

                if ($staged !== null) {
                    $entry['chrome'][$slot->value] = [$staged];
                }
            }
        }

        Cache::put(self::key($token), $entry, now()->addHours(2));

        return true;
    }

    /**
     * Every slot as a (possibly empty) entry list — the shape the preview
     * view's block loop consumes directly.
     *
     * @param  array<string, array{type: string, data: array<string, mixed>}|null>  $chrome
     * @return array<string, list<array{type: string, data: array<string, mixed>}>>
     */
    private function renderReadyChrome(array $chrome): array
    {
        $entries = [];

        foreach (ChromeSlot::cases() as $slot) {
            $entry = $chrome[$slot->value] ?? null;
            $entries[$slot->value] = $entry === null ? [] : [$entry];
        }

        return $entries;
    }
}
