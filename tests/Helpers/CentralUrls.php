<?php

declare(strict_types=1);

use App\Enums\Locale;

/*
 * Addresses on the central marketing domain.
 *
 * In tests/Helpers rather than at the top of one test file (Pest boots this
 * directory for every run): CentralSiteTest, LocaleRoutingTest and
 * ApplyTemplateTest all reach for the same three, and a copy per file is three
 * places to fix when the prefix scheme changes again.
 */

/**
 * The un-prefixed addresses: the negotiating root and the two crawler files,
 * which are the only central pages that carry no language.
 */
function centralUrl(string $path = '/'): string
{
    return 'http://'.test()->centralDomain().$path;
}

/**
 * A page in one language.
 */
function localeUrl(Locale $locale, string $path = ''): string
{
    return centralUrl('/'.$locale->value.$path);
}

/**
 * A named central route in one language.
 *
 * The locale is always passed, never inherited: the URL generator is a
 * singleton for the whole test process, so a bare `route('central.home')` in an
 * assertion resolves against whatever language the PREVIOUS request left in
 * URL::defaults — a test that passes for the wrong reason.
 *
 * @param  array<string, mixed>  $parameters
 */
function localeRoute(string $name, Locale $locale, array $parameters = []): string
{
    return route($name, [...$parameters, 'locale' => $locale->value]);
}
