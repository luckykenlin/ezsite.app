<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\BuildOnboardingProgress;
use App\Actions\StoreCuratorUpload;
use App\Enums\Locale;
use App\Filament\Fabricator\BlockRegistry;
use App\Site\BindResolver;
use App\Site\Blocks\BlockVocabulary;
use App\Site\LeadFormIds;
use App\Site\MediaResolver;
use App\Site\OnboardingProgress;
use App\Site\PostFeed;
use App\Site\SiteCapture;
use App\Site\SiteChrome;
use App\Site\SiteContext;
use App\Site\SiteSettingsLoader;
use App\StockPhotos\NullProvider;
use App\StockPhotos\PexelsProvider;
use App\StockPhotos\StockPhotoProvider;
use Awcodes\Curator\Components\Forms\Uploader;
use Filament\Forms\Components\BaseFileUpload;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(BindResolver::class);
        $this->app->scoped(LeadFormIds::class);
        $this->app->scoped(MediaResolver::class);
        $this->app->scoped(SiteCapture::class);
        $this->app->scoped(SiteChrome::class);
        $this->app->scoped(SiteContext::class);
        $this->app->scoped(SiteSettingsLoader::class);
        // Four surfaces ask about updates on one public page load — the home
        // page's block, the nav entry, the offer popup and the hours notice —
        // and PostFeed answers all four from a single windowed read.
        $this->app->scoped(PostFeed::class);

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

        // Three surfaces ask "how far through setup is this owner?" on a single
        // tenant-panel page load: the panel-wide banner, the dashboard checklist,
        // and that widget's own canView(). Scoped and lazy, so the four queries
        // happen once per request and not at all on requests that ask nothing.
        $this->app->scoped(
            OnboardingProgress::class,
            static fn (): OnboardingProgress => resolve(BuildOnboardingProgress::class)->handle(),
        );
    }

    public function boot(): void
    {
        // The `{locale}` segment every central marketing route carries, for the
        // callers that generate one of those URLs WITHOUT going through
        // SetLocale: the 404 view, robots.txt and sitemap.xml, and anything
        // queued or run from the console. Without a default, `route('central.*')`
        // there throws "Missing required parameter".
        //
        // SetLocale overwrites this per request, so a visitor reading English is
        // never handed a Chinese link.
        URL::defaults(['locale' => Locale::default()->value]);

        // Every Curator upload — every block's image picker, the business logo,
        // the SEO share image, the Media resource, bulk upload — is stored
        // through our own handler instead of the package's, which moves the
        // original temp file to disk untouched. See StoreCuratorUpload.
        //
        // `configureUsing` rather than a subclass: the package constructs
        // `Uploader::make()` on the concrete class in three separate places
        // (the picker modal, the media form, the bulk-upload action), so a
        // subclass of ours would never be the one built.
        Uploader::configureUsing(function (Uploader $uploader): void {
            $uploader->saveUploadedFileUsing(
                fn (BaseFileUpload $component, TemporaryUploadedFile $file): ?array => resolve(StoreCuratorUpload::class)
                    ->handle($component, $file),
            );
        });
    }
}
