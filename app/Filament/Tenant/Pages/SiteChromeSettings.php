<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Actions\SaveSiteChrome;
use App\Filament\Fabricator\PageBlocks\Footer;
use App\Filament\Fabricator\PageBlocks\Header;
use App\Models\SiteSetting;
use BackedEnum;
use Filament\Forms\Components\Builder;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;

/**
 * Editor for the tenant's site-wide header/footer (see SiteChrome). Reuses
 * the Header/Footer block schemas verbatim — variant and bind selectors
 * included — and stores exactly the Fabricator block-entry shape the render
 * loop consumes. An emptied slot stores null, falling back to the default
 * chrome.
 */
final class SiteChromeSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWindow;

    protected static ?string $title = 'Header & footer';

    public function mount(): void
    {
        $settings = SiteSetting::query()->first();

        $this->form->fill([
            'header' => $settings->header ?? [],
            'footer' => $settings->footer ?? [],
        ]);
    }

    protected function components(): array
    {
        return [
            Section::make('Header')
                ->description('Shown at the top of every page. Leave empty to use the default header.')
                ->schema([
                    Builder::make('header')
                        ->hiddenLabel()
                        ->blocks([Header::getBlockSchema()])
                        ->maxItems(1),
                ]),
            Section::make('Footer')
                ->description('Shown at the bottom of every page. Leave empty to use the default footer.')
                ->schema([
                    Builder::make('footer')
                        ->hiddenLabel()
                        ->blocks([Footer::getBlockSchema()])
                        ->maxItems(1),
                ]),
        ];
    }

    protected function persist(array $state): void
    {
        $header = $state['header'] ?? null;
        $footer = $state['footer'] ?? null;

        resolve(SaveSiteChrome::class)->handle(
            is_array($header) ? $header : null,
            is_array($footer) ? $footer : null,
        );
    }

    protected function savedNotificationTitle(): string
    {
        return 'Site header & footer saved';
    }
}
