<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Templates\SiteTemplate;
use Illuminate\Contracts\View\View;

/**
 * The template gallery: all eight, as cards, with a live demo behind each.
 *
 * @see HomeController for why this namespace exists
 */
final class TemplateGalleryController extends Controller
{
    public function __invoke(): View
    {
        return view('central.templates.index', ['templates' => SiteTemplate::cases()]);
    }
}
