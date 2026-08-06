<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Stock Photos
    |--------------------------------------------------------------------------
    |
    | Automatic stock photography for AI-generated site drafts: the draft
    | agent proposes an English search query per image-bearing section, and
    | PopulateDraftImagesJob searches the provider, imports the photos into
    | the tenant's media library and wires them into the blocks. Disabled by
    | default — with no provider key configured the NullProvider is bound and
    | the whole pipeline is inert, so a generated draft simply ships without
    | photos, exactly as it did before this existed.
    |
    | The per-site budgets bound one generation's spend against the provider
    | rate limit (Pexels: 200 requests/hour, 20k/month). `rate_limit_per_hour`
    | sits just under the provider's ceiling so a burst of generations
    | degrades to photo-less drafts instead of provider errors.
    |
    */

    // filter_var: phpunit's <env> overrides arrive as the literal string
    // "false", which env() alone would pass through into a config()->boolean()
    // read and throw. Normalising here keeps the flag readable strictly.
    'enabled' => filter_var(env('STOCK_PHOTOS_ENABLED', false), FILTER_VALIDATE_BOOL),

    'max_photos_per_site' => 12,

    'max_searches_per_site' => 8,

    'rate_limit_per_hour' => 190,

    'http_timeout' => 10,

    // Separate from the total budget above, because they bound different
    // failures. `http_timeout` is the whole request; this is the TCP handshake
    // alone, and it is short because a host that has not answered in three
    // seconds is down rather than slow. Without it a blackholed connect burns
    // the full 10s — and PopulateDraftImages walks its photos serially inside a
    // 120s job, so a handful of those is the difference between a site with
    // photographs and one without.
    'http_connect_timeout' => 3,

    // TOTAL attempts per request, not retries on top of one — this is the
    // number Laravel's `retry()` takes, and reading it the other way silently
    // buys one fewer attempt than intended. Deliberately small: the provider is
    // not on the critical path (every failure degrades to no photo) and the
    // job's timeout is the real budget. Two extra tries turn the common
    // transient — a dropped connection, a 502 from the CDN — from "this site
    // gets no photographs" into a short pause. Only connection errors and 5xx
    // are retried; a 429 means the rate limiter above already lost, and hitting
    // it again would only dig deeper.
    'http_attempts' => 3,

    'http_retry_delay_ms' => 200,

];
