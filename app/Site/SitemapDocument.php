<?php

declare(strict_types=1);

namespace App\Site;

use Illuminate\Http\Response;

/**
 * The sitemap.xml envelope: entries in, a well-formed `<urlset>` response out.
 *
 * The tenant and central sitemaps list different things for different reasons
 * and keep their own controllers — but the XML format is not a decision either
 * of them should own. Before this existed the heredoc lived in both, and only
 * the tenant one emitted `<lastmod>`, so the two sitemaps weren't even the
 * same schema.
 */
final readonly class SitemapDocument
{
    /**
     * @param  iterable<int, array{loc: string, lastmod?: string|null}>  $entries
     *                                                                             absolute URLs; `lastmod` is omitted from the
     *                                                                             XML when null — an empty element tells a
     *                                                                             crawler less than no element
     */
    public function respond(iterable $entries): Response
    {
        $urls = [];

        foreach ($entries as $entry) {
            $lastmod = $entry['lastmod'] ?? null;

            $urls[] = $lastmod === null || $lastmod === ''
                ? sprintf('    <url><loc>%s</loc></url>', e($entry['loc']))
                : sprintf('    <url><loc>%s</loc><lastmod>%s</lastmod></url>', e($entry['loc']), e($lastmod));
        }

        $joined = implode("\n", $urls);

        return response(
            <<<XML
                <?xml version="1.0" encoding="UTF-8"?>
                <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                {$joined}
                </urlset>

                XML,
        )->header('Content-Type', 'application/xml');
    }
}
