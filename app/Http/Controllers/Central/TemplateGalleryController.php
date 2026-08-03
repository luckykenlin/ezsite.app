<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
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
    public function __invoke(): View
    {
        return view('central.templates.index', [
            'templates' => SiteTemplate::cases(),
            'seo' => new SEOData(
                title: 'Website templates for small businesses',
                description: SiteTemplate::libraryCount().' finished websites, one for each trade. Open the live demo, then make it yours in a few minutes.',
                url: route('central.templates.index'),
                enableTitleSuffix: false,
                site_name: config()->string('app.name'),
            ),
        ]);
    }
}
