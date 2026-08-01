<?php

declare(strict_types=1);

namespace App\StockPhotos;

/**
 * The shape of photo a block slot wants. Values are Pexels' own `orientation`
 * parameter values, which happen to be the generic words any provider
 * understands.
 */
enum PhotoOrientation: string
{
    case Landscape = 'landscape';

    case Portrait = 'portrait';

    case Square = 'square';
}
