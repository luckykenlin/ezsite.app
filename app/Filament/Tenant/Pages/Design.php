<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Actions\SaveDesignSelection;
use App\Design\ColorPalette;
use App\Design\StylePreset;
use App\Design\TokenOptions;
use App\Models\Business;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The tenant's design settings: pick a curated style preset, then fine-tune
 * within the enumerated token space. Hidden until a Business profile exists
 * (tokens live on the business row).
 *
 * @property-read Schema $form
 */
final class Design extends Page
{
    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    protected string $view = 'filament.tenant.pages.design';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    public static function canAccess(): bool
    {
        return Business::query()->exists();
    }

    public function mount(): void
    {
        $business = Business::query()->firstOrFail();
        $tokens = $business->design_tokens;

        $this->form->fill([
            'preset' => $tokens->preset?->value,
            'palette' => $tokens->palette->value,
            'font_pair' => $tokens->fontPair->value,
            'radius' => $tokens->radius->value,
            'density' => $tokens->density->value,
            'brand_primary' => $business->brand_primary,
            'brand_secondary' => $business->brand_secondary,
            'brand_accent' => $business->brand_accent,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Style preset')
                    ->description('A curated combination of colors, fonts, shapes and spacing. Applying one replaces the fine-tune choices below.')
                    ->schema([
                        Radio::make('preset')
                            ->hiddenLabel()
                            ->options(TokenOptions::presets())
                            ->descriptions(TokenOptions::presetDescriptions())
                            ->live()
                            // Selecting a preset only previews it into the
                            // fine-tune fields — nothing persists (and the
                            // live site doesn't change) until Save. A stray
                            // click used to restyle the whole site instantly,
                            // with no undo.
                            ->afterStateUpdated(function (Set $set, mixed $state): void {
                                $preset = is_string($state) ? StylePreset::tryFrom($state) : null;

                                if ($preset === null) {
                                    return;
                                }

                                $tokens = $preset->tokens();

                                $set('palette', $tokens->palette->value);
                                $set('font_pair', $tokens->fontPair->value);
                                $set('radius', $tokens->radius->value);
                                $set('density', $tokens->density->value);
                            }),
                    ]),
                Section::make('Fine-tune')
                    ->description('Adjusting any of these detaches the preset — the combination becomes your own.')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('palette')
                                ->options(TokenOptions::palettes())
                                ->selectablePlaceholder(false)
                                ->live()
                                ->columnSpan(1),
                            Select::make('font_pair')
                                ->label('Fonts')
                                ->options(TokenOptions::fontPairs())
                                ->selectablePlaceholder(false)
                                ->columnSpan(1),
                            Select::make('radius')
                                ->label('Corner radius')
                                ->options(TokenOptions::radiusScales())
                                ->selectablePlaceholder(false)
                                ->columnSpan(1),
                            Select::make('density')
                                ->label('Spacing density')
                                ->options(TokenOptions::densities())
                                ->selectablePlaceholder(false)
                                ->columnSpan(1),
                        ]),
                    ]),
                Section::make('Brand colors')
                    ->description('Used when the palette is set to Brand.')
                    ->visible(fn (Get $get): bool => $get('palette') === ColorPalette::Brand->value)
                    ->schema([
                        Grid::make(3)->schema([
                            ColorPicker::make('brand_primary')
                                ->columnSpan(1),
                            ColorPicker::make('brand_secondary')
                                ->columnSpan(1),
                            ColorPicker::make('brand_accent')
                                ->columnSpan(1),
                        ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $business = Business::query()->firstOrFail();

        $business->update([
            'brand_primary' => $data['brand_primary'] ?? $business->brand_primary,
            'brand_secondary' => $data['brand_secondary'] ?? $business->brand_secondary,
            'brand_accent' => $data['brand_accent'] ?? $business->brand_accent,
        ]);

        resolve(SaveDesignSelection::class)->handle($business, $data);

        $this->mount();

        Notification::make()
            ->title('Design saved')
            ->success()
            ->send();
    }
}
