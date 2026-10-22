<?php

declare(strict_types=1);

use App\Themes\Scheme;
use App\Themes\Theme;

/*
 * The default theme's schemes, by slug.
 *
 * In tests/Helpers rather than at the top of one test file (Pest boots this
 * directory for every run): the scheme used to be an enum case any test could
 * name, and some thirty files reach for one — spelling the theme lookup out in
 * each would be thirty places to change when the lookup does.
 */

/**
 * Throws rather than returning null, so a test that names a slug the default
 * theme does not offer fails at the lookup instead of three assertions later
 * on a null property read.
 */
function defaultScheme(string $slug): Scheme
{
    return Theme::Default->definition()->scheme($slug)
        ?? throw new InvalidArgumentException(sprintf('The default theme has no "%s" scheme.', $slug));
}
