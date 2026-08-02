<?php

declare(strict_types=1);

use App\Site\OnboardingProgress;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // `leads.store` is the one non-api endpoint that answers XHR: the
        // public capture forms post to it with fetch when JavaScript is
        // available (resources/js/site/lead-form.ts), and the script needs the
        // 422 body to paint per-field errors. Without this the handler
        // redirects instead, the fetch follows it, and the form silently
        // reports success for a submission that never stored.
        //
        // `expectsJson()` is load-bearing on that second clause — it restores
        // Laravel's own default for this ONE route rather than forcing JSON on
        // it. The same endpoint still serves plain browser posts from the
        // no-JavaScript path, and those must keep getting a redirect with a
        // flashed error bag.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*')
                || ($request->routeIs('leads.store') && $request->expectsJson()),
        );

        $exceptions->render(fn (TenantCouldNotBeIdentifiedException $e): Response => redirect(config()->string('app.url')));

        /*
         * A tenant site with nothing published says "coming soon" rather than
         * 404.
         *
         * ProvisionSiteFromTemplate leaves every page in Draft for review, so a
         * bare 404 was the FIRST thing a brand-new site said — on a link its
         * owner may already have sent to customers. The panel-wide banner tells
         * the owner; this tells everybody else.
         *
         * A 200 with `noindex`, deliberately: a 503 would make the owner's own
         * "is my site working?" check and any uptime monitor read as an outage,
         * which is a worse failure than a placeholder being crawled once.
         *
         * Scoped to PUBLIC tenant paths. The panel's own 404s and the internal
         * `_editor`/`_preview`/`_claim` routes must keep failing like routes,
         * and the tenant check comes first so the OnboardingProgress read (four
         * queries) never happens on the central domain.
         */
        $exceptions->render(function (NotFoundHttpException $e, Request $request): ?Response {
            $isPublicTenantPath = tenant() !== null
                && ! $request->is('admin', 'admin/*')
                && ! str_starts_with($request->path(), '_');

            if (! $isPublicTenantPath || resolve(OnboardingProgress::class)->isLive()) {
                return null;
            }

            return response()->view('site.coming-soon');
        });
    })->create();
