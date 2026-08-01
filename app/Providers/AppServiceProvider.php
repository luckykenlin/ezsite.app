<?php

declare(strict_types=1);

namespace App\Providers;

use App\Filament\Fabricator\BlockRegistry;
use App\Site\BindResolver;
use App\Site\Blocks\BlockVocabulary;
use App\Site\MediaResolver;
use App\Site\SiteChrome;
use App\Site\SiteContext;
use App\StockPhotos\NullProvider;
use App\StockPhotos\PexelsProvider;
use App\StockPhotos\StockPhotoProvider;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(BindResolver::class);
        $this->app->scoped(MediaResolver::class);
        $this->app->scoped(SiteChrome::class);
        $this->app->scoped(SiteContext::class);

        // Keyed by configuration, not environment: with no Pexels key the
        // NullProvider makes the whole stock-photo pipeline inert-but-safe,
        // so nothing else needs to know whether photos are available.
        $this->app->bind(static function (): StockPhotoProvider {
            $key = config('services.pexels.key');

            return is_string($key) && $key !== ''
                ? new PexelsProvider($key)
                : new NullProvider;
        });

        // The one place the two layers are wired together. The block CLASSES are
        // Filament form schemas and stay under App\Filament (Fabricator globs them
        // from there); the ENUMERATION they produce is a domain concept, so the
        // domain gets it injected and never imports the panel. A provider is the
        // right home for that translation — it is the layer allowed to see both.
        //
        // Scoped, and lazily built by the closure: walking every registered block
        // class is not free, and nothing outside a request that renders or edits
        // blocks needs it at all.
        $this->app->scoped(
            BlockVocabulary::class,
            static fn (): BlockVocabulary => new BlockVocabulary(BlockRegistry::contracts()),
        );
    }
}
