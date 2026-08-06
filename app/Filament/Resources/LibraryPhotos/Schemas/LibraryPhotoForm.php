<?php

declare(strict_types=1);

namespace App\Filament\Resources\LibraryPhotos\Schemas;

use App\Enums\PhotoCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Only the fields a curator should touch. Everything else on the row — the
 * file, the dimensions, the palette, the provenance and credit — is measured or
 * reported, not opinion, so editing it by hand would only ever make the record
 * disagree with the photograph.
 *
 * `keywords` is absent for a different reason: the model derives it from these
 * fields on save, so improving a description here is what improves search.
 */
final class LibraryPhotoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('alt')
                    ->label('Description')
                    ->helperText('What the photo shows. This becomes the alt text on every site that uses it, and it is what searches match.')
                    ->maxLength(255),
                TextInput::make('title')
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('Notes')
                    ->helperText('Longer detail, for search only — never rendered on a site.')
                    ->rows(3),
                Select::make('category')
                    ->options(fn (): array => collect(PhotoCategory::cases())
                        ->mapWithKeys(fn (PhotoCategory $category): array => [$category->value => $category->label()])
                        ->all())
                    ->native(false),
                TagsInput::make('tags')
                    ->helperText('Extra words this photo should be findable by.'),
            ]);
    }
}
