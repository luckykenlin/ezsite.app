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
     * Swap only the BLOCKS of an existing preview entry — the chat worker's
     * write path, which lets the canvas repaint after every tool call while a
     * turn is still running.
     *
     * Everything else in the entry (page, chrome, design tokens) is preserved
     * exactly as the editor last pushed it: the worker knows nothing about the
     * inspector's chrome draft or a staged style, and overwriting them with
     * defaults would repaint the canvas in a state the operator never had.
     * False when no entry exists (never pushed, or expired) — the caller skips
     * its repaint signal, since there is nothing on screen to go stale.
     *
     * @param  list<array{key: string, type: string, data: array<string, mixed>}>  $blocks
     */
    public function replaceBlocks(string $token, array $blocks): bool
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
