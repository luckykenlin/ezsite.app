<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Pages\CachePageEditorPreview;
use App\Design\DesignTokens;
use App\Design\ThemeVariables;
use App\Enums\ChromeSlot;
use App\Filament\Fabricator\BindResolver;
use App\Models\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Z3d0X\FilamentFabricator\Facades\FilamentFabricator;

/**
 * Renders the page editor's canvas document: the draft state that
 * {@see CachePageEditorPreview} put in the cache, rendered through the SAME
 * layout chain as the live tenant site (theme variables, fonts, site.css),
 * plus the editor-only `data-block-key` wrappers and the click-to-select
 * script the wrapping editor page talks to.
 *
 * Access model mirrors filament-peek's preview route, which this app already
 * ships: an unguessable random token in a tenant-prefixed cache, so a URL is
 * useless cross-tenant and expires with the editing session.
 */
final class PageEditorPreviewController extends Controller
{
    public function __invoke(Request $request): View
    {
        $payload = Cache::get(CachePageEditorPreview::key((string) $request->query('token')));

        abort_unless(is_array($payload), 404);

        $attributes = $payload['page'] ?? null;
        $blocks = $payload['blocks'] ?? null;
        $keys = $payload['keys'] ?? [];

        abort_unless(is_array($attributes) && is_array($blocks) && is_array($keys), 404);

        $layoutName = $attributes['layout'] ?? null;
        $layout = FilamentFabricator::getLayoutFromName(is_string($layoutName) && $layoutName !== '' ? $layoutName : 'main');

        abort_if($layout === null, 404);

        $page = (new Page)->forceFill([
            'id' => $attributes['id'] ?? null,
            'title' => $attributes['title'] ?? null,
            'layout' => $layoutName,
            'blocks' => array_values($blocks),
        ]);

        $chrome = $payload['chrome'] ?? null;

        $blockKey = $request->query('block');

        if (is_string($blockKey) && $blockKey !== '') {
            return $this->blockFragment($blockKey, array_values($blocks), array_values($keys), is_array($chrome) ? $chrome : null);
        }

        return view('filament.tenant.pages.page-editor-preview', [
            'component' => $layout::getComponent(),
            'page' => $page,
            'editorKeys' => array_values($keys),
            'editorChrome' => is_array($chrome) ? $chrome : null,
            'themeDraft' => $this->themeDraftStyle($payload['design_tokens'] ?? null),
        ]);
    }

    /**
     * A single wrapped block's HTML — the editor's debounced field edits
     * fetch this and patch it into the canvas instead of reloading the whole
     * document.
     *
     * @param  list<mixed>  $blocks
     * @param  list<mixed>  $keys
     * @param  array<array-key, mixed>|null  $chrome
     */
    private function blockFragment(string $blockKey, array $blocks, array $keys, ?array $chrome): View
    {
        $slot = ChromeSlot::fromEditorKey($blockKey);

        if ($slot instanceof ChromeSlot) {
            $entries = $chrome[$slot->value] ?? null;

            abort_unless(is_array($entries) && $entries !== [], 404);

            return view('filament.tenant.pages.page-editor-preview-block', [
                'blocks' => array_values($entries),
                'editorKeys' => [$blockKey],
            ]);
        }

        $index = array_search($blockKey, $keys, true);

        abort_if($index === false || ! array_key_exists($index, $blocks), 404);

        return view('filament.tenant.pages.page-editor-preview-block', [
            'blocks' => [$blocks[$index]],
            'editorKeys' => [$blockKey],
        ]);
    }

    /**
     * A `<style>` override compiled from the editor's unsaved design-token
     * draft. Emitted AFTER the saved theme's HEAD_END style so it wins; all
     * values come from enum constants / validated hexes (see ThemeVariables),
     * so the raw echo is safe.
     */
    private function themeDraftStyle(mixed $tokens): ?HtmlString
    {
        if (! is_array($tokens)) {
            return null;
        }

        // Same request-scoped resolver the rest of the render uses, so the
        // canvas costs no extra business query.
        $business = resolve(BindResolver::class)->business();

        if ($business === null) {
            return null;
        }

        return ThemeVariables::styleFor(DesignTokens::fromArray($tokens), $business, 'data-editor-theme-draft');
    }
}
