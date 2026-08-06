<?php

declare(strict_types=1);

namespace App\Filament\Resources\LibraryPhotos\Pages;

use App\Filament\Resources\LibraryPhotos\LibraryPhotoResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Index only — editing happens in the row's modal. No create action; see
 * {@see LibraryPhotoResource} for why photos are imported, not authored.
 */
final class ListLibraryPhotos extends ListRecords
{
    protected static string $resource = LibraryPhotoResource::class;
}
