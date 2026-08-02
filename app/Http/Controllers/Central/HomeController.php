<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Templates\SiteTemplate;
use Illuminate\Contracts\View\View;

/**
 * The central domain's landing page: what this product is, how it works, and
 * the eight templates a visitor can start from.
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
     * The three templates fanned in the hero. Named rather than sliced off the
     * front of the list: they have to look unlike one another at a glance, and
     * the library happens to open with three warm restaurants.
     */
    private const array FANNED = [
        SiteTemplate::DesignerPortfolio,
        SiteTemplate::ChineseRestaurant,
        SiteTemplate::MassageSpa,
    ];

    public function __invoke(): View
    {
        return view('central.home', [
            'templates' => SiteTemplate::cases(),
            'fanned' => self::FANNED,
        ]);
    }
}
