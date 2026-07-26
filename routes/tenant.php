<?php

declare(strict_types=1);

use App\Http\Controllers\PageController;
use App\Http\Controllers\PageEditorPreviewController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromUnwantedDomains;
use Stancl\Tenancy\Middleware\ScopeSessions;

Route::middleware([
    'web',
    InitializeTenancyByDomainOrSubdomain::class,
    PreventAccessFromUnwantedDomains::class,
    ScopeSessions::class,
])->group(function (): void {
    Route::get('/_editor/preview', PageEditorPreviewController::class)
        ->name('page-editor.preview');

    Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
    Route::get('/robots.txt', RobotsController::class)->name('robots');

    Route::get('/{filamentFabricatorPage?}', PageController::class)
        ->where('filamentFabricatorPage', '.*')
        ->fallback();
});
