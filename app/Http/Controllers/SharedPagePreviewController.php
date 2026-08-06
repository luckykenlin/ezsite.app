<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Contracts\View\View;
use Z3d0X\FilamentFabricator\Facades\FilamentFabricator;

/**
 * The shareable preview: a page's SAVED state rendered through the real tenant
 * layout chain for someone with the link and no account — the "send this to
 * the client before it goes live" flow.
 *
 * Gated by a temporary signed URL (the `signed` middleware on the route), not
 * by auth: the whole point is that the recipient has no login. The signature
 * covers the page id and carries its own expiry, so a leaked link dies on its
 * own and cannot be re-aimed at another page. RLS scopes the lookup to the
 * tenant the domain resolved, so a signed link is also useless cross-tenant.
 *
 * Renders `pages.blocks` — the last SAVED version, draft status included —
 * never the editor's working draft: what the operator shares is what they
 * deliberately saved, not whatever half-typed state their editor holds. This
 * is the one place a draft page is visible outside the editor's own iframe;
 * {@see PageController} still hard-404s drafts for everyone else.
 */
final class SharedPagePreviewController extends Controller
{
    public function __invoke(Page $page): View
    {
        $layout = FilamentFabricator::getLayoutFromName(
            $page->layout === '' ? 'main' : $page->layout,
        );

        abort_if($layout === null, 404);

        // The plain live-site render path: no editor keys, so the layout emits
        // the real chrome and the full SEO head — the preview should look
        // exactly like publishing would.
        return view('shared-page-preview', [
            'component' => $layout::getComponent(),
            'page' => $page,
        ]);
    }
}
