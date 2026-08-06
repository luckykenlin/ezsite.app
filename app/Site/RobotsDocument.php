<?php

declare(strict_types=1);

namespace App\Site;

use Illuminate\Http\Response;

/**
 * The robots.txt envelope: disallow rules and a sitemap URL in, a plain-text
 * response out.
 *
 * Same split as {@see SitemapDocument}: the tenant and central controllers
 * rightly own their POLICIES (what stays out of the index, which sitemap to
 * advertise), but the file format was being maintained twice.
 */
final readonly class RobotsDocument
{
    /**
     * @param  list<string>  $disallow  path prefixes to keep out of the index
     */
    public function respond(array $disallow, string $sitemapUrl): Response
    {
        $rules = implode("\n", array_map(
            static fn (string $path): string => 'Disallow: '.$path,
            $disallow,
        ));

        return response(
            <<<TXT
                User-agent: *
                Allow: /
                {$rules}

                Sitemap: {$sitemapUrl}

                TXT,
        )->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
