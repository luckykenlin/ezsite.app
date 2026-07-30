<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A site draft could not be turned into a page. Nothing is persisted either way.
 *
 * Abstract because the two reasons want different handling, and sharing one type
 * meant {@see \App\Actions\GenerateSiteDraft} could not tell them apart:
 *
 *  - {@see SiteDraftUnusable} — the model's output did not yield a page.
 *    RETRYABLE; one fresh attempt usually lands.
 *  - {@see SiteDraftRefused} — the site's own state forbids the write.
 *    NOT retryable; nothing about asking again changes it.
 *
 * Catch this base where the distinction does not matter — reporting to the
 * operator, which is all {@see \App\Jobs\GenerateSiteDraftJob} does.
 */
abstract class SiteDraftInvalid extends RuntimeException
{
    //
}
