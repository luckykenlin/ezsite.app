<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Reserved subdomains
    |--------------------------------------------------------------------------
    |
    | Labels no signup may claim. Two kinds live here: hosts this product will
    | want for itself (www, app, api, admin, status, docs) and hosts whose
    | meaning is already fixed by infrastructure (mail, mx, smtp, cdn) — a
    | tenant on one of those does not merely look wrong, it intercepts mail or
    | shadows a certificate.
    |
    | The `demo-` PREFIX is reserved separately, in ValidateSubdomain, because
    | it is a namespace rather than a list: every SiteTemplate mints
    | `demo-{template}`, so reserving the prefix keeps the demo sites safe from
    | a signup without this list needing an entry per template.
    |
    */

    'reserved_subdomains' => [
        'www', 'admin', 'app', 'api', 'assets', 'auth', 'blog', 'cdn', 'central',
        'dashboard', 'demo', 'dev', 'docs', 'ftp', 'help', 'imap', 'mail', 'mx',
        'ns', 'ns1', 'ns2', 'panel', 'pop', 'smtp', 'staging', 'static', 'status',
        'support', 'test', 'webmail', 'ezsite',
    ],

    /*
    |--------------------------------------------------------------------------
    | Signup rate limit
    |--------------------------------------------------------------------------
    |
    | The apply wizard creates an account, a tenant and a site with no email
    | verification and no manual approval — instant provisioning is the whole
    | product promise. This is what stands in for that missing friction, keyed
    | on IP and email together.
    |
    */

    'signup' => [
        'max_attempts' => (int) env('TEMPLATE_SIGNUP_MAX_ATTEMPTS', 3),
        'decay_minutes' => (int) env('TEMPLATE_SIGNUP_DECAY_MINUTES', 60),
    ],

];
