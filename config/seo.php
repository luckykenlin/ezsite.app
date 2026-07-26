<?php

declare(strict_types=1);

use RalphJSmit\Laravel\SEO\Models\SEO;

/*
 * ralphjsmit/laravel-seo, used for its RENDERING layer only: we hand it a
 * fully-populated SEOData (see App\Actions\BuildPageSeoData) and it emits the
 * title/description/canonical/robots/OpenGraph/Twitter/JSON-LD tags.
 *
 * The package's `seo` table and `HasSEO` trait are deliberately NOT used: that
 * table is polymorphic with no tenant_id, which our fail-closed RLS guard
 * (tests/Feature/Tenancy/RlsPolicyTest) rejects. Per-page overrides live in
 * columns on `pages` instead.
 *
 * IMPORTANT: this file is a CENTRAL singleton — one value for every tenant.
 * Anything that differs per tenant (site name, share image, favicon, title
 * suffix) must be set on the SEOData object, never here.
 */
return [
    /*
     * Only read by the unused HasSEO trait; kept so the package's own default
     * resolves if a future model ever opts into the DB layer.
     */
    'model' => SEO::class,

    /*
     * Per tenant (the tenant's Business name) — set on SEOData.
     */
    'site_name' => null,

    /*
     * A root-relative path, so it is correct on every tenant domain. Served by
     * App\Http\Controllers\SitemapController.
     */
    'sitemap' => '/sitemap.xml',

    'canonical_link' => true,

    'robots' => [
        'default' => 'max-snippet:-1,max-image-preview:large,max-video-preview:-1',

        /*
         * Must stay false: pages flagged not-indexable send their own
         * `noindex,nofollow` through SEOData->robots.
         */
        'force_default' => false,
    ],

    /*
     * Per tenant — Fabricator renders its own favicon link when configured.
     */
    'favicon' => null,

    'title' => [
        /*
         * BuildPageSeoData always supplies a title, so URL guessing would only
         * ever produce a worse one.
         */
        'infer_title_from_url' => false,

        /*
         * The suffix is the tenant's business name, so it is composed in
         * BuildPageSeoData with enableTitleSuffix: false.
         */
        'suffix' => '',

        'homepage_title' => null,
    ],

    'description' => [
        /*
         * Per tenant (business tagline/description) — set on SEOData.
         */
        'fallback' => null,
    ],

    'image' => [
        /*
         * Per tenant (page share image or business logo) — set on SEOData.
         */
        'fallback' => null,
    ],

    'author' => [
        'fallback' => null,
    ],

    'twitter' => [
        '@username' => null,
    ],
];
