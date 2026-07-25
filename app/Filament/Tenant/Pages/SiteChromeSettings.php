<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Filament\Fabricator\PageBlocks\Footer;
use App\Filament\Fabricator\PageBlocks\Header;
use App\Models\SiteSetting;
use BackedEnum;
use Filament\Forms\Components\Builder;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Editor for the tenant's site-wide header/footer (see SiteChrome). Reuses
 * the Header/Footer block schemas verbatim — variant and bind selectors
 * included — and stores exactly the Fabricator block-entry shape the render
 * loop consumes. An emptied slot stores null, falling back to the default
 * chrome.
 *
 * @property-read Schema $form
 */
final class SiteChromeSettings extends Page
{
    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    protected string $view = 'filament.tenant.pages.site-chrome-settings';

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

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
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
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $header = $data['header'] ?? null;
        $footer = $data['footer'] ?? null;

        SiteSetting::query()->updateOrCreate(
            ['tenant_id' => tenant('id')],
            [
                'header' => is_array($header) && $header !== [] ? array_values($header) : null,
                'footer' => is_array($footer) && $footer !== [] ? array_values($footer) : null,
            ],
        );

        Notification::make()
            ->title('Site header & footer saved')
            ->success()
            ->send();
    }
}
