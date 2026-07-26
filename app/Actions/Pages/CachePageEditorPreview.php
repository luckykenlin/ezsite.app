<?php

declare(strict_types=1);

namespace App\Actions\Pages;

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
            // Stored render-ready: each slot is a (possibly empty) entry list.
            'chrome' => $chrome === null ? null : [
                'header' => isset($chrome['header']) ? [$chrome['header']] : [],
                'footer' => isset($chrome['footer']) ? [$chrome['footer']] : [],
            ],
            'design_tokens' => $designTokens,
        ], now()->addHours(2));
    }
}
