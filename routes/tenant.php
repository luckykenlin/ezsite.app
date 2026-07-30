<?php

declare(strict_types=1);

use App\Http\Controllers\PageController;
use App\Http\Controllers\PageEditorChatStreamController;
use App\Http\Controllers\PageEditorPreviewController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StoreLeadController;
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

    // The chat reply as the worker writes it. Its own route rather than
    // Livewire's wire:stream — see the controller for why.
    Route::get('/_editor/chat-stream', PageEditorChatStreamController::class)
        ->name('page-editor.chat-stream');

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
