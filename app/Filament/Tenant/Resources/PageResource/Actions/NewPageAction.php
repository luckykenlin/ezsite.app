<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PageResource\Actions;

use App\Actions\Pages\CreateThemePage;
use App\Filament\Tenant\Resources\PageResource\Pages\PageCanvas;
use App\Themes\CurrentTheme;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;

/**
 * The site canvas's "Add a page" picker: a grid of the current theme's
 * designed pages — each card a live thumbnail of that page rendered through
 * the tenant's own theme — plus Blank, which reproduces the old name-only
 * gesture exactly. Blank is a fixed first card (value `blank`), not a theme
 * page: it has nothing for a theme to design.
 *
 * Extracted from {@see PageCanvas} when the modal grew the picker (the
 * threshold in `.ai/rules/filament.md`); it takes the canvas page the way its
 * siblings take the editor. Selection is a ViewField of radio-styled cards
 * rather than N `wire:click`-and-create buttons so the choice submits with
 * the form: one round trip, Filament's own validation, and a title the
 * operator can override in the same breath.
 *
 * The title is OPTIONAL, unlike the old modal: picking "About" and pressing
 * Create is the one-click path, and the page's own name is the obvious
 * default. Blank with no title falls back the same way ("Untitled page").
 */
final readonly class NewPageAction
{
    /**
     * The picker's form value for the blank card. Not a theme page key; a
     * theme that named a page `blank` would collide, which `ThemeTest` forbids.
     */
    public const string BLANK = 'blank';

    public static function make(PageCanvas $canvas): Action
    {
        $pages = resolve(CurrentTheme::class)->definition()->pages;

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
                ViewField::make('page')
                    ->view('filament.tenant.pages.partials.page-canvas-page-picker', [
                        'previewToken' => $canvas->previewToken,
                        'blank' => self::BLANK,
                        'pages' => $pages,
                    ])
                    ->default(self::BLANK)
                    ->required()
                    ->in([self::BLANK, ...array_keys($pages)]),
                TextInput::make('title')
                    ->label(__('Page name'))
                    ->maxLength(120)
                    ->placeholder(__("Defaults to the layout's own name"))
                    ->helperText(__('The web address is generated from the name.')),
            ])
            ->action(function (array $data, CreateThemePage $create) use ($canvas, $pages): void {
                // Validated by ->in() above, so an unknown key cannot reach
                // here; only the blank card resolves to null.
                $page = $pages[Arr::string($data, 'page')] ?? null;
                // Not Arr::string(): an empty title arrives as an explicit
                // null, which that helper rejects rather than defaulting.
                $title = $data['title'] ?? null;

                $created = $create->handle($page, is_string($title) ? $title : null);

                $canvas->forgetCards();
                $canvas->dispatch('page-canvas:page-created', id: (int) $created->id);
            });
    }
}
