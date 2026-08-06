<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Posts;

use App\Filament\Tenant\Resources\Posts\Pages\CreatePost;
use App\Filament\Tenant\Resources\Posts\Pages\EditPost;
use App\Filament\Tenant\Resources\Posts\Pages\ListPosts;
use App\Filament\Tenant\Resources\Posts\Schemas\PostForm;
use App\Filament\Tenant\Resources\Posts\Tables\PostsTable;
use App\Models\Post;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Updates — what the model still calls a Post.
 *
 * The class and the table keep the old name (renaming them would ripple through
 * the tenancy docs and six test files and buy nothing), but every word an operator
 * reads says "update". That matches Google's own wording for the same object and,
 * more to the point, what a salon owner would call it: nobody running a nail bar
 * thinks they are blogging.
 */
final class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = 'Updates';

    protected static ?string $modelLabel = 'update';

    protected static ?string $pluralModelLabel = 'updates';

    public static function form(Schema $schema): Schema
    {
        return PostForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PostsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosts::route('/'),
            // Real pages, not modals: eighteen fields across five sections, two of
            // them conditional, do not belong in a dialog.
            'create' => CreatePost::route('/create'),
            'edit' => EditPost::route('/{record}/edit'),
        ];
    }
}
