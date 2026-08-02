<?php

declare(strict_types=1);

use App\Http\Controllers\Central\HomeController;
use App\Http\Controllers\Central\RobotsController;
use App\Http\Controllers\Central\SitemapController;
use App\Http\Controllers\Central\TemplateDetailController;
use App\Http\Controllers\Central\TemplateGalleryController;
use App\Livewire\Central\ApplyTemplate;
use Illuminate\Http\RedirectResponse;
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

$centralDomains = array_values(array_filter(Config::array('tenancy.identification.central_domains'), is_string(...)));
$canonicalDomain = $centralDomains[0] ?? null;

if ($canonicalDomain !== null) {
    // Named routes are registered against the FIRST central domain only.
    // Registering them per domain would re-register the same names, and
    // Laravel's RouteCollection keeps the LAST one per name — so the moment a
    // second domain (say `www.`) was added, every route() call and both
    // crawler files would silently start advertising it.
    Route::domain($canonicalDomain)->group(function (): void {
        Route::get('/', HomeController::class)->name('central.home');

        Route::get('/templates', TemplateGalleryController::class)->name('central.templates.index');
        Route::get('/templates/{template}', TemplateDetailController::class)->name('central.templates.show');

        // The guided apply form. Full-page Livewire: the subdomain field
        // checks availability as you type, which plain Blade cannot do, and a
        // Filament schema outside a panel would drag panel styling onto the
        // marketing surface.
        Route::get('/start/{template}', ApplyTemplate::class)->name('central.templates.start');

        Route::get('/robots.txt', RobotsController::class)->name('central.robots');
        Route::get('/sitemap.xml', SitemapController::class)->name('central.sitemap');
    });
}

// Any further central domain is an alias: everything redirects to the
// canonical host, path intact, so search engines see one central site.
foreach (array_slice($centralDomains, 1) as $domain) {
    Route::domain($domain)->group(function () use ($canonicalDomain): void {
        Route::any('/{path?}', fn (string $path = ''): RedirectResponse => redirect()->away(
            'https://'.$canonicalDomain.'/'.mb_ltrim($path, '/'),
            301,
        ))->where('path', '.*');
    });
}
