<?php

declare(strict_types=1);

namespace App\Filament\Resources\LibraryPhotos;

use App\Filament\Resources\LibraryPhotos\Pages\ListLibraryPhotos;
use App\Filament\Resources\LibraryPhotos\Schemas\LibraryPhotoForm;
use App\Filament\Resources\LibraryPhotos\Tables\LibraryPhotosTable;
use App\Models\LibraryPhoto;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Curation for the shared photo library — the central-panel counterpart of the
 * read-only tenant resource.
 *
 * Imports publish themselves, so this is where the catalogue is made better
 * rather than made at all: correct a wrong description (which also corrects
 * what the photo is findable by, see {@see LibraryPhoto::booted()}), fix a bad
 * category guess, or unpublish a photo that should stop being offered.
 *
 * No create page. A photo without provenance is a photo nobody can attribute,
 * and rows arrive from the provider through
 * {@see \App\Actions\Library\FindOrImportLibraryPhoto} — `library:import` is
 * the way to add to the catalogue.
 */
final class LibraryPhotoResource extends Resource
{
    protected static ?string $model = LibraryPhoto::class;

    protected static ?string $recordTitleAttribute = 'alt';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Photo library';

    protected static ?string $modelLabel = 'photo';

    public static function form(Schema $schema): Schema
    {
        return LibraryPhotoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LibraryPhotosTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLibraryPhotos::route('/'),
        ];
    }
}
