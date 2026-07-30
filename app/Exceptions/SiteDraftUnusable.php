<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * The model\'s output did not yield a usable page: no structured output at all, or
 * too little survived sanitization to publish (too few blocks, no hero).
 *
 * RETRYABLE, and deliberately covers both. It is tempting to treat "nothing
 * survived sanitization" as a sparse-business-profile problem that a retry cannot
 * fix, but empirically it is the SYMPTOM of provider drift: a model whose
 * structured output is only prompt-enforced (DeepSeek among them) returns
 * something shaped almost-right, every block gets dropped, and the next attempt is
 * fine. Splitting those apart would remove the retry from the case it was added
 * for, so they share a type until there is a signal that actually distinguishes
 * them.
 */
final class SiteDraftUnusable extends SiteDraftInvalid
{
    //
}
