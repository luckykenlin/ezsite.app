<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Locale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puts a central marketing request into the language its URL asks for.
 *
 * Attached to the `{locale}` route group in routes/web.php and nowhere else.
 * Not appended to the `web` group, which routes/tenant.php opts into as well —
 * a tenant request has no business being re-localised, and the tenant panels
 * translate every label globally.
 *
 * Two things happen besides setLocale(), and both are load-bearing:
 *
 * - `URL::defaults()` makes the current language the default for the
 *   `{locale}` parameter, which is why all ~23 `route('central.*')` call sites
 *   needed no change at all. The un-prefixed default lives in
 *   {@see \App\Providers\AppServiceProvider} for the paths that never reach
 *   this middleware (the 404 view, the crawler files, console and queue).
 *
 * - `forgetParameter()` drops the segment from the route's parameter bag.
 *   Laravel dispatches controller arguments POSITIONALLY — ControllerDispatcher
 *   ends in `$controller->{$method}(...array_values($parameters))` — so leaving
 *   `locale` in place would hand the string "zh" to
 *   `TemplateDetailController::__invoke(SiteTemplate $template)`. Dropping it
 *   here keeps every central controller signature free of a parameter none of
 *   them use. (Livewire is unaffected either way: its ImplicitRouteBinding
 *   matches mount arguments by NAME.)
 */
final class SetLocale
{
    /**
     * A year. Long enough that a returning visitor never re-negotiates, short
     * enough to eventually forgive someone who picked the wrong one on a shared
     * machine.
     */
    private const int COOKIE_LIFETIME_MINUTES = 60 * 24 * 365;

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $parameter = $request->route()?->parameter('locale');
        $locale = (is_string($parameter) ? Locale::tryFrom($parameter) : null) ?? Locale::default();

        App::setLocale($locale->value);
        URL::defaults(['locale' => $locale->value]);

        $request->route()?->forgetParameter('locale');

        // Only on a change: a Set-Cookie on every response would make the
        // marketing pages uncacheable by any proxy in front of them.
        if ($request->cookie(Locale::COOKIE) !== $locale->value) {
            Cookie::queue(Locale::COOKIE, $locale->value, self::COOKIE_LIFETIME_MINUTES);
        }

        return $next($request);
    }
}
