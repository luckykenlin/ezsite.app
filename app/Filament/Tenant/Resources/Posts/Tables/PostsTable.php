<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Posts\Tables;

use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Filament\Tenant\Resources\Posts\Actions\PublishPostAction;
use App\Models\Post;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->description(fn (Post $record): ?string => $record->excerpt),
                TextColumn::make('kind')
                    ->badge(),
                TextColumn::make('status')
                    ->badge(),
                // A column rather than a third status badge: "finished" is not a
                // state the operator sets, it is a fact about the dates. And an
                // operator who cannot see which offers have run out will publish a
                // second one on top of a live one.
                IconColumn::make('expired')
                    ->label('Finished')
                    ->state(fn (Post $record): bool => $record->isExpired())
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                    ->falseIcon(Heroicon::OutlinedMinusSmall)
                    ->trueColor('gray')
                    ->falseColor('gray'),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(PostStatus::class),
                SelectFilter::make('kind')
                    ->options(PostKind::class),
            ])
            ->recordActions([
                // No before-publish hook: the table publishes the LAST SAVED
                // content, the composer saves what you are looking at first.
                PublishPostAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
