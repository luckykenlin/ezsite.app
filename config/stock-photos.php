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

];
