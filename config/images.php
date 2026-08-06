<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Stored image ceiling
    |--------------------------------------------------------------------------
    |
    | Every image that lands on a disk — a stock photo, a logo, a chat
    | attachment — is scaled down to fit this width and re-encoded before it is
    | written. Nothing on a rendered page is ever wider than a full-bleed hero,
    | so pixels past this point are bytes a visitor downloads and cannot see.
    |
    | Scaled DOWN only: a narrower image keeps its own size and is never
    | enlarged into blurriness.
    |
    */

    'max_width' => (int) env('IMAGE_MAX_WIDTH', 1880),

    /*
    |--------------------------------------------------------------------------
    | WebP quality
    |--------------------------------------------------------------------------
    |
    | 78 is a deliberate point on the curve, measured against the stock library:
    | Pexels' `large2x` renditions arrive near-lossless at 2–3 MB for a 1880px
    | photograph, and re-encoding them here at this quality lands them between
    | 120 and 250 KB with no difference anyone has been able to see at 2x zoom.
    | Lower starts showing banding in skies and gradients, which is exactly what
    | a hero photograph is full of.
    |
    */

    'quality' => (int) env('IMAGE_QUALITY', 78),

];
