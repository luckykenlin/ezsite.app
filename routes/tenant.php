<?php

declare(strict_types=1);

use App\Http\Controllers\ClaimSiteController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PageEditorChatStreamController;
use App\Http\Controllers\PageEditorPreviewController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SharedPagePreviewController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StoreLeadController;
use App\Http\Middleware\RememberLeadAttribution;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromUnwantedDomains;
use Stancl\Tenancy\Middleware\ScopeSessions;

Route::middleware([
    'web',
    InitializeTenancyByDomainOrSubdomain::class,
    PreventAccessFromUnwantedDomains::class,
    ScopeSessions::class,
    // Records first-touch UTM/referrer on the landing request, so an enquiry
    // submitted three pages later still knows which ad earned it.
    RememberLeadAttribution::class,
])->group(function (): void {
    Route::get('/_editor/preview', PageEditorPreviewController::class)
        ->name('page-editor.preview');

    // The chat reply as the worker writes it. Its own route rather than
    // Livewire's wire:stream — see the controller for why.
    Route::get('/_editor/chat-stream', PageEditorChatStreamController::class)
        ->name('page-editor.chat-stream');

    // The shareable stakeholder preview: saved state, draft status included,
    // gated by the URL's own temporary signature instead of a login. Signed
    // RELATIVE, deliberately: the signature covers path + query but not the
    // host, so a link shared before a tenant moves to a custom domain still
    // validates after — the tenant scope itself comes from the domain
    // middleware + RLS, not from the signature.
    Route::get('/_preview/{page}', SharedPagePreviewController::class)
        ->middleware('signed:relative')
        ->name('page.shared-preview');

    // The signup wizard's hand-off: signed on the central domain, spent here.
    // Relative for the same reason the shared preview is — a signature that
    // covered the host could never be minted on the domain that mints it.
    Route::get('/_claim/{user}', ClaimSiteController::class)
        ->middleware('signed:relative')
        ->name('site.claim');

    Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
    Route::get('/robots.txt', RobotsController::class)->name('robots');

    // The public contact form. Throttled per IP: a handful of enquiries a
    // minute is generous for a human and useless for a bot.
    Route::post('/_leads', StoreLeadController::class)
        ->middleware('throttle:5,1')
        ->name('leads.store');

    Route::get('/{filamentFabricatorPage?}', PageController::class)
        ->where('filamentFabricatorPage', '.*')
        ->fallback();
});
