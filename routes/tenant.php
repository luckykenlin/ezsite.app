<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromUnwantedDomains;
use Stancl\Tenancy\Middleware\ScopeSessions;
use Z3d0X\FilamentFabricator\Http\Controllers\PageController;

Route::middleware([
    'web',
    InitializeTenancyByDomainOrSubdomain::class,
    PreventAccessFromUnwantedDomains::class,
    ScopeSessions::class,
])->group(function (): void {
    Route::get('/{filamentFabricatorPage?}', PageController::class)
        ->where('filamentFabricatorPage', '.*')
        ->fallback();
});
