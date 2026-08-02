<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\BuildSitemapUrls;
use App\Site\SitemapDocument;
use Illuminate\Http\Response;

/**
 * The tenant site's sitemap: every published, indexable page and update of
 * the CURRENT tenant ({@see BuildSitemapUrls}).
 *
 * Generated per request rather than written to a file: a tenant has a handful
 * of pages and a few dozen updates a year, and a stale file is worse than a
 * cheap query.
 */
final class SitemapController extends Controller
{
    public function __invoke(BuildSitemapUrls $urls, SitemapDocument $document): Response
    {
        return $document->respond($urls->handle());
    }
}
