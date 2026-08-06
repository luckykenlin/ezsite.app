<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Enums\Locale;
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
 *
 * Every page appears once per language, as its own <url> entry — the shape
 * Google actually reads. The `hreflang` relationships between those entries are
 * declared in each page's <head> ({@see \App\Site\LocaleUrls}) rather than as
 * `xhtml:link` children here, which keeps SitemapDocument a plain list of
 * locations shared with the tenant sitemap.
 *
 * The bare `/` is absent on purpose: it is a redirect, and a sitemap that
 * advertises a redirect asks a crawler to spend a fetch learning nothing.
 */
final class SitemapController extends Controller
{
    public function __invoke(SitemapDocument $document): Response
    {
        return $document->respond(
            collect(Locale::cases())
                ->flatMap(static fn (Locale $locale): array => [
                    route('central.home', ['locale' => $locale->value]),
                    route('central.templates.index', ['locale' => $locale->value]),
                    ...array_map(
                        static fn (SiteTemplate $template): string => route('central.templates.show', [
                            'locale' => $locale->value,
                            'template' => $template,
                        ]),
                        SiteTemplate::cases(),
                    ),
                ])
                ->map(static fn (string $url): array => ['loc' => $url, 'lastmod' => null])
                ->all(),
        );
    }
}
