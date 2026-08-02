<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Actions\Templates\FillTemplatePlaceholders;
use App\Http\Controllers\Controller;
use App\Templates\SiteTemplate;
use App\Templates\TemplateGallery;
use Illuminate\Contracts\View\View;
use RalphJSmit\Laravel\SEO\Support\SEOData;

/**
 * One template in full: a large screenshot, what the look is, what the
 * template gives you, and the two things a visitor can do next — open the
 * live demo, or start building on it.
 *
 * The route binds {@see SiteTemplate} directly, so an unknown slug 404s in
 * routing and never reaches this class.
 *
 * @see HomeController for why this namespace exists
 */
final class TemplateDetailController extends Controller
{
    public function __invoke(SiteTemplate $template, FillTemplatePlaceholders $fillPlaceholders, TemplateGallery $gallery): View
    {
        $definition = $template->definition();
        $desktop = $gallery->screenshot($template, TemplateGallery::DESKTOP_WIDTH);

        return view('central.templates.show', [
            'template' => $template,
            'definition' => $definition,
            // Filled, not raw: this page lists the template's pages by title,
            // and a raw title is a literal `{business_name}` printed on the
            // marketing site. Filling with no answers yields the demo
            // profile's copy, which is what the demo site behind the link says.
            'pages' => $fillPlaceholders->handle($definition)['pages'],
            'desktop' => $desktop,
            'mobile' => $gallery->screenshot($template, TemplateGallery::MOBILE_WIDTH),
            'demoUrl' => $gallery->demoUrl($template),
            'seo' => new SEOData(
                title: $template->label().' website template',
                description: $template->description(),
                image: $desktop,
                url: route('central.templates.show', $template),
                enableTitleSuffix: false,
                site_name: config()->string('app.name'),
            ),
        ]);
    }
}
