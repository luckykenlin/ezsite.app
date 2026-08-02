<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException;
use Symfony\Component\HttpFoundation\Response;

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
    })->create();
