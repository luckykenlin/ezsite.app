<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Site\LocaleNegotiator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The marketing URLs as they were before they carried a language: `/templates`,
 * `/templates/{template}`, `/start/{template}`.
 *
 * Every inbound link, bookmark and indexed page predating the `{locale}` prefix
 * points at these, and the alternative to keeping them is a wall of 404s. The
 * whole path is forwarded intact — query string included — so a link with UTM
 * parameters still lands on the right page with its attribution
 * ({@see \App\Http\Middleware\RememberLeadAttribution} reads the query on the
 * LANDING request, which after this redirect is the localised one).
 *
 * A class rather than a closure in routes/web.php: a closure route cannot be
 * serialised, and `php artisan route:cache` would fail on the whole file.
 *
 * @see HomeController for why this namespace exists
 */
final class LegacyLocaleRedirectController extends Controller
{
    public function __invoke(Request $request, LocaleNegotiator $negotiator): RedirectResponse
    {
        return redirect()
            ->to('/'.$negotiator->handle($request)->value.$request->getRequestUri())
            ->header('Vary', 'Accept-Language, Cookie');
    }
}
