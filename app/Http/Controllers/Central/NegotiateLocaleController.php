<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Site\LocaleNegotiator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The bare central root. Sends a first-time visitor to the language their
 * browser asked for, and a returning one back to the language they chose.
 *
 * A 302, never a 301: the destination depends on who is asking, so it must not
 * be cached as this URL's permanent identity. `Vary` tells any proxy in between
 * the same thing.
 *
 * This is the only page on the marketing site that guesses. Every other URL
 * states its language in the path, which is what lets a crawler index both —
 * and `/` is the hreflang x-default for exactly that reason.
 *
 * @see HomeController for why this namespace exists
 */
final class NegotiateLocaleController extends Controller
{
    public function __invoke(Request $request, LocaleNegotiator $negotiator): RedirectResponse
    {
        return to_route('central.home', ['locale' => $negotiator->handle($request)->value])
            ->header('Vary', 'Accept-Language, Cookie');
    }
}
