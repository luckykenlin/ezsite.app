<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PageResource\Actions;

use App\Actions\SaveDesignSelection;
use App\Design\StylePreset;
use App\Design\TokenKey;
use App\Design\TokenOptions;
use App\Filament\Tenant\Resources\PageResource\Pages\PageEditor;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;

/**
 * The in-editor Design modal: every token change re-themes the CANVAS only
 * (through {@see PageEditor::previewDesign()}), so the operator sees the new
 * look on their real page before committing. "Apply to site" is the single
 * write path, and it defers the preset-vs-custom decision to
 * {@see SaveDesignSelection} — the same rule the Design settings page uses.
 */
final readonly class DesignAction
{
    public static function make(PageEditor $editor): Action
    {
        $preview = function (Get $get) use ($editor): void {
            $draft = ['preset' => $get('preset')];

            foreach (TokenKey::values() as $key) {
                $draft[$key] = $get($key);
            }

            $editor->previewDesign($draft);
        };

        return Action::make('design')
            ->label('Design')
            ->color('gray')
            ->icon(Heroicon::OutlinedSwatch)
            ->visible(fn (): bool => $editor->hasBusinessProfile())
            ->modalSubmitActionLabel('Apply to site')
            ->fillForm(function () use ($editor): array {
                $tokens = $editor->businessOrFail()->design_tokens;
                $state = ['preset' => $tokens->preset?->value];

                foreach (TokenKey::cases() as $key) {
                    $state[$key->value] = $key->valueOn($tokens);
                }

                return $state;
            })
            ->schema([
                Select::make('preset')
                    ->label('Style preset')
                    ->options(TokenOptions::presets())
                    ->live()
                    ->placeholder('Custom')
                    ->afterStateUpdated(function (Set $set, Get $get, mixed $state) use ($preview): void {
                        $preset = is_string($state) ? StylePreset::tryFrom($state) : null;

                        if ($preset !== null) {
                            $tokens = $preset->tokens();

                            foreach (TokenKey::cases() as $key) {
                                $set($key->value, $key->valueOn($tokens));
                            }
                        }

                        $preview($get);
                    }),
                ...array_map(
                    static fn (TokenKey $key): Select => self::tokenSelect($key, $preview),
                    TokenKey::cases(),
                ),
            ])
            ->action(function (array $data) use ($editor): void {
                resolve(SaveDesignSelection::class)->handle($editor->businessOrFail(), $data);

                $editor->clearDesignDraft();
                $editor->refreshCanvas();

                Notification::make()
                    ->title('Design applied to the whole site')
                    ->success()
                    ->send();
            });
    }

    /**
     * One fine-tune token: always has a value, and repaints the canvas the
     * moment it changes.
     *
     * @param  callable(Get): void  $preview
     */
    private static function tokenSelect(TokenKey $key, callable $preview): Select
    {
        return Select::make($key->value)
            ->label($key->label())
            ->options(TokenOptions::for($key))
            ->selectablePlaceholder(false)
            ->live()
            ->afterStateUpdated(fn (Get $get) => $preview($get));
    }
}
