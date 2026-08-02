<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remembers where a visitor came from, once per session, so a lead submitted
 * three pages later still carries its origin.
 *
 * Attribution data only exists on the LANDING request: the visitor arrives on
 * `/?utm_source=google&utm_medium=cpc` with an external referrer, then browses
 * to `/services` and submits there — by which point the query string is gone
 * and the referrer is the site itself. Reading either at submit time would
 * attribute every paid lead to "direct", which is precisely the measurement
 * the operator is trying to make.
 *
 * FIRST touch wins, deliberately. A visitor who arrives from an ad, leaves,
 * and returns by typing the domain was still earned by the ad. Last-touch
 * would credit the second visit and make the ad look worthless.
 *
 * GET only, and never on the editor/preview routes: a POST carries no
 * attribution of its own, and overwriting on the enquiry request itself would
 * replace the real landing page with `/_leads`.
 */
final class RememberLeadAttribution
{
    /**
     * The session key the captured attribution lives under.
     */
    public const string SESSION_KEY = 'lead_attribution';

    /**
     * The UTM parameters recorded, in `query key => lead column` form. The
     * five Google-standard ones and nothing else — an open-ended sweep of the
     * query string would put arbitrary visitor-controlled keys in the session.
     */
    private const array UTM_PARAMETERS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
    ];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldRecord($request)) {
            $request->session()->put(self::SESSION_KEY, $this->attribution($request));
        }

        return $next($request);
    }

    private function shouldRecord(Request $request): bool
    {
        return $request->isMethod('GET')
            && ! $request->session()->has(self::SESSION_KEY)
            && ! str_starts_with(mb_ltrim($request->path(), '/'), '_');
    }

    /**
     * @return array<string, string|null>
     */
    private function attribution(Request $request): array
    {
        $attribution = [];

        foreach (self::UTM_PARAMETERS as $parameter) {
            $attribution[$parameter] = $this->trimmed($request->query($parameter));
        }

        // An internal referrer means the visit started somewhere we already
        // recorded (or nowhere), so only an external one is worth storing.
        $referrer = $this->trimmed($request->headers->get('referer'));

        $attribution['referrer'] = $referrer !== null && $this->isExternal($referrer, $request)
            ? $referrer
            : null;

        $attribution['landing_path'] = mb_substr($request->getRequestUri(), 0, 255);

        return $attribution;
    }

    private function isExternal(string $referrer, Request $request): bool
    {
        return parse_url($referrer, PHP_URL_HOST) !== $request->getHost();
    }

    /**
     * Query values arrive as `string|array|null`; only a non-empty scalar is
     * usable, and it is truncated to the column width before it can overflow.
     */
    private function trimmed(mixed $value): ?string
    {
        if (! is_string($value) || mb_trim($value) === '') {
            return null;
        }

        return mb_substr(mb_trim($value), 0, 255);
    }
}
