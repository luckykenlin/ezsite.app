<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a review ask reaches a customer.
 *
 * One case today, because only the counter card has shipped — but the column
 * is a closed set like every other status/kind column in the app, and
 * `docs/reviews-module.md` already names the next members (an email ask, an
 * SMS ask) that the sending layer will add. An enum from day one is what
 * keeps `'link'` from being spelled three ways by then.
 */
enum ReviewChannel: string
{
    case Link = 'link';
}
