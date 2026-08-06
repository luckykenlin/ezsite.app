<?php

declare(strict_types=1);

namespace App\Actions;

use App\Site\BindResolver;
use App\Site\PublicUrl;
use App\Site\SeoFallbacks;
use RalphJSmit\Laravel\SEO\Support\SEOData;

/**
 * The <head> of the updates index — the third and simplest SEO surface,
 * beside {@see BuildPageSeoData} and {@see BuildPostSeoData}.
 *
 * A listing has no SEO columns of its own, so everything here is the
 * {@see SeoFallbacks} chain over the tenant's Business: suffixed title, stock
 * description. No image on purpose — the index aggregates updates, and
 * electing one of their covers to represent all of them would make the share
 * card lie about what the link opens.
 */
final readonly class BuildPostIndexSeoData
{
    public function __construct(
        private BindResolver $bindResolver,
        private SeoFallbacks $fallbacks,
    ) {
        //
    }

    public function handle(): SEOData
    {
        $business = $this->bindResolver->business();

        return new SEOData(
            title: $this->fallbacks->title(__('Updates'), $business),
            description: $this->fallbacks->description(null, $business),
            url: PublicUrl::updatesIndex(),
            enableTitleSuffix: false,
            site_name: $business?->name,
        );
    }
}
