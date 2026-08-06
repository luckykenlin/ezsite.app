<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\LibraryPhotos\Pages;

use App\Filament\Tenant\Resources\LibraryPhotos\LibraryPhotoResource;
use Filament\Resources\Pages\ListRecords;

/**
 * The only page this resource has — no create, no edit, no view. See
 * {@see LibraryPhotoResource} for why the shared catalogue is read-only here.
 */
final class ListLibraryPhotos extends ListRecords
{
    protected static string $resource = LibraryPhotoResource::class;
}
