<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * robots.txt for the tenant site: crawlers get the whole public site, the
 * admin panel and the editor's preview route stay out of the index, and the
 * sitemap is advertised on the tenant's own domain.
 */
final class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        return response(
            <<<TXT
                User-agent: *
                Allow: /
                Disallow: /admin
                Disallow: /_editor

                Sitemap: {$this->sitemapUrl()}

                TXT,
        )->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    private function sitemapUrl(): string
    {
        return url('/sitemap.xml');
    }
}
