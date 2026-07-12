<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

$centralDomains = array_filter(Config::array('tenancy.identification.central_domains'), is_string(...));

foreach ($centralDomains as $domain) {
    Route::domain($domain)->group(function (): void {
        // Central domain routes
        Route::view('/', 'central.welcome');
    });
}
