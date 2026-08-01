<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\LibraryPhotos\Tables;

use App\Actions\Library\AdoptLibraryPhoto;
use App\Enums\PhotoCategory;
use App\Models\LibraryPhoto;
use App\Models\Media;
use App\StockPhotos\PhotoOrientation;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class LibraryPhotosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(self::publishedOnly(...))
            ->columns([
                // A full URL from the shared library disk, which is served off
                // the central domain — so the same <img> works here on a tenant
                // domain. Nothing on a published page ever points at it.
                ImageColumn::make('preview')
                    ->label('Photo')
                    ->state(fn (LibraryPhoto $record): string => $record->previewUrl())
                    ->height(64),
                TextColumn::make('alt')
                    ->label('Description')
                    ->wrap()
                    // The denormalised blob, not just the visible column: the
                    // operator types "cafe" and means the tags and the original
                    // search query too.
                    ->searchable(['alt', 'keywords']),
                TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (PhotoCategory $state): string => $state->label()),
                TextColumn::make('orientation')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('dominant_color')
                    ->label('Colour')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('usage_count')
                    ->label('Used on sites')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('usage_count')
            ->filters([
                SelectFilter::make('category')
                    ->options(fn (): array => collect(PhotoCategory::cases())
                        ->mapWithKeys(fn (PhotoCategory $category): array => [$category->value => $category->label()])
                        ->all()),
                SelectFilter::make('orientation')
                    ->options(fn (): array => collect(PhotoOrientation::cases())
                        ->mapWithKeys(fn (PhotoOrientation $orientation): array => [$orientation->value => ucfirst($orientation->value)])
                        ->all()),
                TernaryFilter::make('is_dark')
                    ->label('Dark enough for overlaid text'),
            ])
            ->recordActions([
                self::adoptAction(),
            ]);
    }

    /**
     * Curated-out photos stay in the catalogue so they are not re-imported, but
     * they are never offered again — the same rule
     * {@see \App\Actions\Library\FindLibraryPhotos} obeys.
     *
     * @param  Builder<LibraryPhoto>  $query
     * @return Builder<LibraryPhoto>
     */
    private static function publishedOnly(Builder $query): Builder
    {
        return $query->published();
    }

    /**
     * Copy the photo into this tenant's own media library. Idempotent, so a
     * second click reports the same media id instead of a second file — and the
     * id is worth showing, because it is what the chat assistant and the block
     * pickers both address a photo by.
     */
    private static function adoptAction(): Action
    {
        return Action::make('adopt')
            ->label('Add to my media')
            ->icon(Heroicon::OutlinedPlusCircle)
            ->action(function (LibraryPhoto $record): void {
                $media = resolve(AdoptLibraryPhoto::class)->handle($record);

                if (! $media instanceof Media) {
                    Notification::make()
                        ->danger()
                        ->title('That photo could not be copied')
                        ->body('Its file is missing from the shared library. Try another one.')
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Added to your media library')
                    ->body(sprintf('It is media #%d — pick it from any image field.', $media->id))
                    ->send();
            });
    }
}
