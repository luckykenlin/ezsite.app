<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * The site's state forbids generating over it — currently only: the home page is
 * already published, and overwriting a live page is not something a background job
 * gets to decide.
 *
 * NOT retryable, and never reached by the retry anyway (the check runs after it).
 * A distinct type so that stays true by construction rather than by statement
 * order, and so a caller can tell "ask again" from "this is not going to work".
 */
final class SiteDraftRefused extends SiteDraftInvalid
{
    //
}
