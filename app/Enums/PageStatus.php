<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Publication state of a tenant page. Drafts are visible in the page builder
 * but 404 on the public site until published.
 */
enum PageStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
