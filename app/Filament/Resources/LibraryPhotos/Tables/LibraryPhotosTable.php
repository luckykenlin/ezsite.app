<?php

declare(strict_types=1);

namespace App\Filament\Resources\LibraryPhotos\Tables;

use App\Enums\PhotoCategory;
use App\Models\LibraryPhoto;
use App\StockPhotos\PhotoOrientation;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class LibraryPhotosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('preview')
                    ->label('Photo')
                    ->state(fn (LibraryPhoto $record): string => $record->previewUrl())
                    ->height(64),
                TextColumn::make('alt')
                    ->label('Description')
                    ->wrap()
                    ->searchable(['alt', 'keywords']),
                TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (PhotoCategory $state): string => $state->label()),
                TextColumn::make('orientation')
                    ->badge()
                    ->toggleable(),
                IconColumn::make('published_at')
                    ->label('Offered')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('usage_count')
                    ->label('Sites using it')
                    ->sortable(),
                TextColumn::make('photographer_name')
                    ->label('Credit')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Imported')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('category')
                    ->options(fn (): array => collect(PhotoCategory::cases())
                        ->mapWithKeys(fn (PhotoCategory $category): array => [$category->value => $category->label()])
                        ->all()),
                SelectFilter::make('orientation')
                    ->options(fn (): array => collect(PhotoOrientation::cases())
                        ->mapWithKeys(fn (PhotoOrientation $orientation): array => [$orientation->value => ucfirst($orientation->value)])
                        ->all()),
                TernaryFilter::make('published_at')
                    ->label('Offered to sites')
                    ->nullable(),
            ])
            ->recordActions([
                EditAction::make(),
                self::publishToggleAction(),
                // Deleting is the harsher option and deliberately still
                // available: an image that should never have been fetched has to
                // be removable. Tenant media already adopted from it survives
                // (curator.library_photo_id is nullOnDelete), so a live page
                // never breaks because of a curation decision.
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Unpublishing is the normal curation verb: the row stays, so the photo is
     * not re-imported by the next matching provider search, but nothing offers
     * it again.
     */
    private static function publishToggleAction(): Action
    {
        return Action::make('togglePublished')
            ->label(fn (LibraryPhoto $record): string => $record->published_at === null ? 'Offer again' : 'Stop offering')
            ->icon(fn (LibraryPhoto $record): Heroicon => $record->published_at === null ? Heroicon::OutlinedEye : Heroicon::OutlinedEyeSlash)
            ->requiresConfirmation()
            ->action(fn (LibraryPhoto $record) => $record->update([
                'published_at' => $record->published_at === null ? now() : null,
            ]));
    }
}
