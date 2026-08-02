<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Site\SitemapDocument;
use App\Templates\SiteTemplate;
use Illuminate\Http\Response;

/**
 * The central sitemap: the landing page, the gallery, and one detail page per
 * template. A fixed, enumerable list — there is no content model behind the
 * marketing site, which is exactly why it can be generated from the enum.
 *
 * `/start/{template}` is excluded on purpose: a signup form has nothing to
 * rank for, and indexing it would compete with the detail page that should.
 */
final class SitemapController extends Controller
{
    public function __invoke(SitemapDocument $document): Response
    {
        return $document->respond(
            collect([route('central.home'), route('central.templates.index')])
                ->merge(array_map(
                    static fn (SiteTemplate $template): string => route('central.templates.show', $template),
                    SiteTemplate::cases(),
                ))
                ->map(static fn (string $url): array => ['loc' => $url, 'lastmod' => null])
                ->all(),
        );
    }
}
