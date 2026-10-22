<?php

declare(strict_types=1);

namespace App\Themes;

use App\Models\Tenant;

/**
 * The theme the current tenant renders through — the one question every
 * theme-aware seam asks, answered in one place.
 *
 * Deliberately NOT a scoped container instance that memoizes the answer, unlike
 * `SiteChrome` or `BindResolver`: nothing flushes scoped instances when
 * `RunInTenant` switches tenants inside one process, and a cached theme would
 * follow a queue worker from one tenant's job into the next. The lookup is a
 * single enum read off the already-loaded tenant model, so there is nothing
 * worth caching; reading `tenant()` on every call is what makes it correct.
 *
 * Outside a tenant context (the central panel, the 404 page, a console
 * command) the answer is the default theme, so callers never special-case
 * "no tenant".
 *
 * `$override` exists for tests only: with a single theme in the library there
 * is no tenant to point at a different one, and the seams that read the theme
 * (the vocabulary filter, the variant selector) still need to be proven to
 * follow it. A test binds `new CurrentTheme($definition)` as the container
 * instance; production never passes it.
 */
final readonly class CurrentTheme
{
    public function __construct(private ?ThemeDefinition $override = null)
    {
        //
    }

    public function theme(): Theme
    {
        $tenant = tenant();

        return $tenant instanceof Tenant ? $tenant->theme : Theme::Default;
    }

    public function definition(): ThemeDefinition
    {
        return $this->override ?? $this->theme()->definition();
    }
}
