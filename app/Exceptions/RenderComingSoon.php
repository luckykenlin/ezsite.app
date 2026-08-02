<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Site\OnboardingProgress;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A tenant site with nothing published says "coming soon" rather than 404.
 *
 * ProvisionSiteFromTemplate leaves every page in Draft for review, so a bare
 * 404 was the FIRST thing a brand-new site said — on a link its owner may
 * already have sent to customers. The panel-wide banner tells the owner; this
 * tells everybody else.
 *
 * A 200 with `noindex`, deliberately: a 503 would make the owner's own "is my
 * site working?" check and any uptime monitor read as an outage, which is a
 * worse failure than a placeholder being crawled once.
 *
 * Scoped to PUBLIC tenant paths. The panel's own 404s and the internal
 * `_editor`/`_preview`/`_claim` routes must keep failing like routes, and the
 * tenant check comes first so the OnboardingProgress read (four queries)
 * never happens on the central domain.
 *
 * An invokable renderer registered in bootstrap/app.php — a class rather than
 * a closure so the path-classification and is-live rules are unit-testable.
 */
final class RenderComingSoon
{
    public function __invoke(NotFoundHttpException $e, Request $request): ?Response
    {
        if (! $this->isPublicTenantPath($request) || resolve(OnboardingProgress::class)->isLive()) {
            return null;
        }

        return response()->view('site.coming-soon');
    }

    private function isPublicTenantPath(Request $request): bool
    {
        return tenant() !== null
            && ! $request->is('admin', 'admin/*')
            && ! str_starts_with($request->path(), '_');
    }
}
