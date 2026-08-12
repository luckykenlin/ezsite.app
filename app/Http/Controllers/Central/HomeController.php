<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Site\LocaleUrls;
use App\Templates\SiteTemplate;
use Illuminate\Contracts\View\View;
use RalphJSmit\Laravel\SEO\Support\SEOData;

/**
 * The central domain's landing page: what this product is, how it works, and
 * the templates a visitor can start from.
 *
 * Every other controller in `app/Http/Controllers` serves a TENANT site behind
 * the tenancy middleware; these live under `Central\` because the separation is
 * the point. Nothing here may touch a tenant-scoped model — there is no tenant,
 * so RLS is not scoping anything and a stray `Page::query()` would read every
 * row in the installation.
 */
final class HomeController extends Controller
{
    /**
     * The three demos framed on the made-with wall. Named rather than sliced
     * off the front of the list: they have to look unlike one another at a
     * glance, and the library happens to open with three warm restaurants.
     */
    private const array FEATURED = [
        SiteTemplate::ChineseRestaurant,
        SiteTemplate::NailSalon,
        SiteTemplate::DesignerPortfolio,
    ];

    public function __invoke(LocaleUrls $localeUrls): View
    {
        return view('central.home', [
            'templates' => SiteTemplate::cases(),
            'featured' => self::FEATURED,
            // Built here rather than in the view, like the other two central
            // pages: the <head> of a page is the controller's business, and
            // the hero headline reads it back so the promise on the page and
            // the promise in the search result cannot drift apart.
            'seo' => new SEOData(
                title: (string) __('marketing.home.hero.title'),
                description: (string) __('marketing.home.meta_description'),
                url: route('central.home'),
                enableTitleSuffix: false,
                site_name: config()->string('app.name'),
                locale: $localeUrls->locale()->openGraphLocale(),
                alternates: $localeUrls->alternates(),
            ),
        ]);
    }
}
