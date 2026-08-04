<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Site\LocaleUrls;
use App\Templates\SiteTemplate;
use Illuminate\Contracts\View\View;
use RalphJSmit\Laravel\SEO\Support\SEOData;

/**
 * The template gallery: every template, as cards, with a live demo behind each.
 *
 * @see HomeController for why this namespace exists
 */
final class TemplateGalleryController extends Controller
{
    public function __invoke(LocaleUrls $localeUrls): View
    {
        return view('central.templates.index', [
            'templates' => SiteTemplate::cases(),
            'seo' => new SEOData(
                title: (string) __('marketing.gallery.meta_title'),
                description: (string) __('marketing.gallery.meta_description', ['count' => SiteTemplate::libraryCount()]),
                url: route('central.templates.index'),
                enableTitleSuffix: false,
                site_name: config()->string('app.name'),
                locale: $localeUrls->locale()->openGraphLocale(),
                alternates: $localeUrls->alternates(),
            ),
        ]);
    }
}
