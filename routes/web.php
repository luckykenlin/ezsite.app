<?php

declare(strict_types=1);

use App\Http\Controllers\Central\HomeController;
use App\Http\Controllers\Central\RobotsController;
use App\Http\Controllers\Central\SitemapController;
use App\Http\Controllers\Central\TemplateDetailController;
use App\Http\Controllers\Central\TemplateGalleryController;
use App\Livewire\Central\ApplyTemplate;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

/*
 * The CENTRAL domain only. Tenant routes live in routes/tenant.php, mounted
 * separately by TenancyServiceProvider.
 *
 * The ->domain() constraint is load-bearing, not decorative: `/` exists in
 * both files, and two routes with the same method and URI and no domain
 * constraint silently overwrite each other in the RouteCollection. It is what
 * keeps this landing page off every tenant's front door.
 */

$centralDomains = array_filter(Config::array('tenancy.identification.central_domains'), is_string(...));

foreach ($centralDomains as $domain) {
    Route::domain($domain)->group(function (): void {
        Route::get('/', HomeController::class)->name('central.home');

        Route::get('/templates', TemplateGalleryController::class)->name('central.templates');
        Route::get('/templates/{template}', TemplateDetailController::class)->name('central.templates.show');

        // The guided apply form. Full-page Livewire: the subdomain field
        // checks availability as you type, which plain Blade cannot do, and a
        // Filament schema outside a panel would drag panel styling onto the
        // marketing surface.
        Route::get('/start/{template}', ApplyTemplate::class)->name('central.start');

        Route::get('/robots.txt', RobotsController::class)->name('central.robots');
        Route::get('/sitemap.xml', SitemapController::class)->name('central.sitemap');
    });
}
