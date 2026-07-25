<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an AI site draft is unusable after sanitization (too few
 * surviving blocks, or no hero). The operator retries generation; nothing
 * is persisted.
 */
final class SiteDraftInvalid extends RuntimeException
{
    //
}
