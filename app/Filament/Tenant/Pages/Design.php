<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Actions\ApplyStylePreset;
use App\Actions\UpdateDesignTokens;
use App\Design\ColorPalette;
use App\Design\FontPair;
use App\Design\RadiusScale;
use App\Design\SpacingDensity;
use App\Design\StylePreset;
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

    /**
     * The enumerated select/radio options for every design token — shared
     * with the page editor's Design modal so both surfaces stay in sync.
     *
     * @return array<string, array<string, string>>
     */
    public static function tokenOptions(): array
    {
        return [
            'preset' => collect(StylePreset::cases())->mapWithKeys(
                fn (StylePreset $preset): array => [$preset->value => $preset->label()],
            )->all(),
            'preset_descriptions' => collect(StylePreset::cases())->mapWithKeys(
                fn (StylePreset $preset): array => [$preset->value => $preset->description()],
            )->all(),
            'palette' => collect(ColorPalette::cases())->mapWithKeys(
                fn (ColorPalette $palette): array => [$palette->value => str($palette->value)->headline()->toString()],
            )->all(),
            'font_pair' => collect(FontPair::cases())->mapWithKeys(
                fn (FontPair $pair): array => [$pair->value => $pair->headingFamily().' + '.$pair->bodyFamily()],
            )->all(),
            'radius' => collect(RadiusScale::cases())->mapWithKeys(
                fn (RadiusScale $radius): array => [$radius->value => str($radius->value)->headline()->toString()],
            )->all(),
            'density' => collect(SpacingDensity::cases())->mapWithKeys(
                fn (SpacingDensity $density): array => [$density->value => str($density->value)->headline()->toString()],
            )->all(),
        ];
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
                            ->options(self::tokenOptions()['preset'])
                            ->descriptions(self::tokenOptions()['preset_descriptions'])
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
                                ->options(self::tokenOptions()['palette'])
                                ->selectablePlaceholder(false)
                                ->live()
                                ->columnSpan(1),
                            Select::make('font_pair')
                                ->label('Fonts')
                                ->options(self::tokenOptions()['font_pair'])
                                ->selectablePlaceholder(false)
                                ->columnSpan(1),
                            Select::make('radius')
                                ->label('Corner radius')
                                ->options(self::tokenOptions()['radius'])
                                ->selectablePlaceholder(false)
                                ->columnSpan(1),
                            Select::make('density')
                                ->label('Spacing density')
                                ->options(self::tokenOptions()['density'])
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

        // A preset whose token bundle still matches the fine-tune fields is
        // persisted as that preset; any divergence saves as a custom
        // combination, detaching the preset marker (UpdateDesignTokens).
        $preset = is_string($data['preset'] ?? null) ? StylePreset::tryFrom($data['preset']) : null;

        if ($preset !== null && $this->matchesPreset($preset, $data)) {
            resolve(ApplyStylePreset::class)->handle($business, $preset);
        } else {
            resolve(UpdateDesignTokens::class)->handle($business, array_filter([
                'palette' => is_string($data['palette'] ?? null) ? $data['palette'] : null,
                'font_pair' => is_string($data['font_pair'] ?? null) ? $data['font_pair'] : null,
                'radius' => is_string($data['radius'] ?? null) ? $data['radius'] : null,
                'density' => is_string($data['density'] ?? null) ? $data['density'] : null,
            ], fn (?string $value): bool => $value !== null));
        }

        $this->mount();

        Notification::make()
            ->title('Design saved')
            ->success()
            ->send();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function matchesPreset(StylePreset $preset, array $data): bool
    {
        $tokens = $preset->tokens();

        return ($data['palette'] ?? null) === $tokens->palette->value
            && ($data['font_pair'] ?? null) === $tokens->fontPair->value
            && ($data['radius'] ?? null) === $tokens->radius->value
            && ($data['density'] ?? null) === $tokens->density->value;
    }
}
