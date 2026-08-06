<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\LibraryPhotos;

use App\Filament\Tenant\Resources\LibraryPhotos\Pages\ListLibraryPhotos;
use App\Filament\Tenant\Resources\LibraryPhotos\Tables\LibraryPhotosTable;
use App\Models\LibraryPhoto;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * The operator's window onto the shared photo library: browse, filter, and take
 * a copy into their own media library.
 *
 * READ-ONLY on purpose, and the only resource in this panel that is. The
 * catalogue is shared by every tenant, so letting one operator rename or delete
 * a row would let them edit every other site's photo metadata. Curation lives
 * in the central panel; here the only verb is "add to my media", which creates
 * a row the tenant genuinely owns
 * ({@see \App\Actions\Library\AdoptLibraryPhoto}).
 *
 * Sits next to Curator's own "Media" item in the navigation, which is the
 * distinction the operator needs to see: Media is theirs, Photo library is
 * everyone's.
 */
final class LibraryPhotoResource extends Resource
{
    protected static ?string $model = LibraryPhoto::class;

    protected static ?string $recordTitleAttribute = 'alt';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Photo library';

    protected static ?string $modelLabel = 'photo';

    public static function table(Table $table): Table
    {
        return LibraryPhotosTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(mixed $record): bool
    {
        return false;
    }

    public static function canDelete(mixed $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
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
