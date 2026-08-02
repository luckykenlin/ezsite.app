/**
 * The public tenant site's only JavaScript.
 *
 * The site shipped none at all until lead capture grew past a single
 * bottom-of-page form, and the bar for adding to this file stays high: every
 * feature here must degrade to working HTML, because a small-business site
 * that breaks without JS is worse than one that never had it.
 *
 * Registered panel-wide by App\Providers\FilamentServiceProvider through
 * FilamentFabricator::registerScripts(), which means it also loads inside the
 * page editor's canvas iframe — `initSite` bails out there.
 *
 * A thin entry on purpose: everything it does lives in `site/`, so the
 * behaviour is importable by tests while this file's `document` access is not.
 */

import { initSite } from './site/boot';

// `defer` guarantees parsing is done; the readyState check covers a future
// caller that loads this eagerly.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initSite();
    });
} else {
    initSite();
}
