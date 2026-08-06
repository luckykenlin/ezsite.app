<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Actions\SaveDesignSelection;
use App\Design\ColorPalette;
use App\Design\StylePreset;
use App\Design\TokenKey;
use App\Design\TokenOptions;
use App\Models\Business;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * The tenant's design settings: pick a curated style preset, then fine-tune
 * within the enumerated token space. Hidden until a Business profile exists
 * (tokens live on the business row).
 */
final class Design extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    public static function canAccess(): bool
    {
        return Business::query()->exists();
    }

    public function mount(): void
    {
        $business = Business::query()->firstOrFail();
        $tokens = $business->design_tokens;

        $state = ['preset' => $tokens->preset?->value];

        foreach (TokenKey::cases() as $key) {
            $state[$key->value] = $key->valueOn($tokens);
        }

        $this->form->fill([
            ...$state,
            'brand_primary' => $business->brand_primary,
            'brand_secondary' => $business->brand_secondary,
            'brand_accent' => $business->brand_accent,
        ]);
    }

    protected function components(): array
    {
        return [
            Section::make('Style preset')
                ->description('A curated combination of colors, fonts, shapes and spacing. Applying one replaces the fine-tune choices below.')
                ->schema([
                    Radio::make('preset')
                        ->hiddenLabel()
                        ->options(TokenOptions::presets())
                        ->descriptions($this->presetSwatchDescriptions())
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

                            foreach (TokenKey::cases() as $key) {
                                $set($key->value, $key->valueOn($tokens));
                            }
                        }),
                ]),
            Section::make('Fine-tune')
                ->description('Adjusting any of these detaches the preset — the combination becomes your own.')
                ->schema([
                    Grid::make(2)->schema(array_map(
                        static fn (TokenKey $key): Select => Select::make($key->value)
                            ->label($key->label())
                            ->options(TokenOptions::for($key))
                            ->selectablePlaceholder(false)
                            // Only the palette is live, and only because the
                            // Brand colors section below watches it. The rest
                            // are read on Save, so a round trip per keystroke
                            // would buy nothing.
                            ->live($key === TokenKey::Palette)
                            ->columnSpan(1),
                        TokenKey::cases(),
                    )),
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
        ];
    }

    protected function persist(array $state): void
    {
        $business = Business::query()->firstOrFail();

        // The whole form state goes through as-is: SaveDesignSelection owns
        // the brand-hex write too, so this page and the chat rail cannot
        // disagree about what a design save means.
        resolve(SaveDesignSelection::class)->handle($business, $state);

        // Refill from what was actually stored, so a preset applied through
        // the fine-tune fields reads back as the preset.
        $this->mount();
    }

    protected function savedNotificationTitle(): string
    {
        return 'Design saved';
    }

    /**
     * Each preset's one-line description with its palette in front of it — four
     * colour dots, so choosing a look is done by eye instead of by reading.
     * Inline styles because the colours are dynamic (one OKLCH value per
     * palette, from enum constants — nothing user-authored reaches the
     * attribute) and the panel's compiled stylesheet cannot carry arbitrary
     * values.
     *
     * @return array<string, HtmlString>
     */
    private function presetSwatchDescriptions(): array
    {
        $descriptions = [];

        foreach (StylePreset::cases() as $preset) {
            $colors = $preset->tokens()->palette->colors();

            $dots = implode('', array_map(
                static fn (string $variable): string => sprintf(
                    '<span style="display:inline-block;width:0.875rem;height:0.875rem;border-radius:9999px;border:1px solid rgba(0,0,0,0.15);background:%s"></span>',
                    e($colors[$variable]),
                ),
                ['--color-primary', '--color-secondary', '--color-accent', '--color-base-200'],
            ));

            $descriptions[$preset->value] = new HtmlString(sprintf(
                '<span style="display:inline-flex;gap:0.25rem;align-items:center;margin-right:0.5rem;vertical-align:middle">%s</span>%s',
                $dots,
                e($preset->description()),
            ));
        }

        return $descriptions;
    }
}
