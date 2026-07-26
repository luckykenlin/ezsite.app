<?php

declare(strict_types=1);

namespace App\Providers;

use App\Filament\Fabricator\BindResolver;
use App\Filament\Fabricator\SiteChrome;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(BindResolver::class);
        $this->app->scoped(SiteChrome::class);
    }

    public function boot(): void
    {
        //
    }
}
