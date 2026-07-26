<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PageStatus;
use App\Models\Page;
use Illuminate\Http\Response;

/**
 * The tenant site's sitemap: every published, indexable page of the CURRENT
 * tenant — RLS scopes the query, so each tenant domain serves its own list
 * with no filtering in this class.
 *
 * Generated per request rather than written to a file: a tenant has a handful
 * of pages, and a stale file is worse than a cheap query.
 */
final class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = Page::query()
            ->where('status', PageStatus::Published)
            ->where('is_indexable', true)
            ->orderBy('id')
            ->get()
            ->map(fn (Page $page): string => sprintf(
                '    <url><loc>%s</loc><lastmod>%s</lastmod></url>',
                e(url($page->getUrl())),
                e($page->updated_at->toAtomString()),
            ))
            ->implode("\n");

        return response(
            <<<XML
                <?xml version="1.0" encoding="UTF-8"?>
                <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                {$urls}
                </urlset>

                XML,
        )->header('Content-Type', 'application/xml');
    }
}
