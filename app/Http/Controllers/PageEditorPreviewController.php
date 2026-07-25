<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Pages\CachePageEditorPreview;
use App\Models\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        return view('filament.tenant.pages.page-editor-preview', [
            'component' => $layout::getComponent(),
            'page' => $page,
            'editorKeys' => array_values($keys),
        ]);
    }
}
