<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Staleness
    |--------------------------------------------------------------------------
    |
    | How long a dated section stays worth showing. Past this, the home page's
    | "Latest updates" block hides itself rather than advertise a business that
    | may have closed — a strip whose freshest item is dated eight months ago
    | converts WORSE than no strip at all, which is the exact inverse of what
    | the section is for.
    |
    | A quarter is the honest line for a local business: a salon that posted in
    | the last three months is plainly open; one that last posted in January is
    | a question mark in September.
    |
    | Configurable because it is a judgement call, not a design constraint —
    | unlike the block's own item counts, which live on the block class because
    | each one has to lay out across seven style presets.
    |
    */

    'stale_after_days' => (int) env('UPDATES_STALE_AFTER_DAYS', 90),

];
