<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PageResource\Actions;

use App\Actions\Pages\CreatePresetPage;
use App\Filament\Tenant\Resources\PageResource\Pages\PageCanvas;
use App\Templates\PagePreset;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;

/**
 * The site canvas's "Add a page" picker: a grid of page-type presets — each
 * card a live thumbnail of that preset rendered through the tenant's own
 * theme — plus Blank, which reproduces the old name-only gesture exactly.
 *
 * Extracted from {@see PageCanvas} when the modal grew the picker (the
 * threshold in `.ai/rules/filament.md`); it takes the canvas page the way its
 * siblings take the editor. Selection is a ViewField of radio-styled cards
 * rather than N `wire:click`-and-create buttons so the choice submits with
 * the form: one round trip, Filament's own validation, and a title the
 * operator can override in the same breath.
 *
 * The title is OPTIONAL, unlike the old modal: picking "About" and pressing
 * Create is the one-click path, and the preset's own name is the obvious
 * default. Blank with no title falls back the same way ("Untitled page").
 */
final readonly class NewPageAction
{
    public static function make(PageCanvas $canvas): Action
    {
        return Action::make('newPage')
            ->label(__('New page'))
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading(__('Add a page'))
            ->modalDescription(__('Start from a ready-made layout, or from nothing. It is created as a hidden draft either way.'))
            ->modalWidth(Width::FourExtraLarge)
            ->modalSubmitActionLabel(__('Create page'))
            // Replaces the default mount behaviour, so the fill() it would
            // have done rides along: the re-put covers a canvas tab left open
            // past the preview cache's two-hour TTL, without which every
            // thumbnail iframe in this modal would 404.
            ->mountUsing(function (?Schema $schema) use ($canvas): void {
                $canvas->cachePreviewPayload();
                $schema?->fill();
            })
            ->schema([
                ViewField::make('preset')
                    ->view('filament.tenant.pages.partials.page-canvas-preset-picker', [
                        'previewToken' => $canvas->previewToken,
                    ])
                    ->default(PagePreset::Blank->value)
                    ->required()
                    ->in(array_column(PagePreset::cases(), 'value')),
                TextInput::make('title')
                    ->label(__('Page name'))
                    ->maxLength(120)
                    ->placeholder(__("Defaults to the layout's own name"))
                    ->helperText(__('The web address is generated from the name.')),
            ])
            ->action(function (array $data) use ($canvas): void {
                $preset = PagePreset::from(Arr::string($data, 'preset'));
                // Not Arr::string(): an empty title arrives as an explicit
                // null, which that helper rejects rather than defaulting.
                $title = $data['title'] ?? null;

                $page = resolve(CreatePresetPage::class)->handle($preset, is_string($title) ? $title : null);

                $canvas->forgetCards();
                $canvas->dispatch('page-canvas:page-created', id: (int) $page->id);
            });
    }
}
