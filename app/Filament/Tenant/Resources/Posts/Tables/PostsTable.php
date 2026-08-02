<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Posts\Tables;

use App\Actions\Posts\PublishPost;
use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
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
                // Publishes the LAST SAVED content — the same asymmetry PublishPage
                // documents for pages (the table publishes what is stored, the
                // composer publishes what you are looking at), through one action.
                Action::make('publish')
                    ->label(fn (Post $record): string => $record->isPublished() ? __('Unpublish') : __('Publish'))
                    ->icon(fn (Post $record): Heroicon => $record->isPublished() ? Heroicon::OutlinedEyeSlash : Heroicon::OutlinedRocketLaunch)
                    ->color(fn (Post $record): string => $record->isPublished() ? 'gray' : 'primary')
                    ->requiresConfirmation(fn (Post $record): bool => $record->isPublished())
                    ->action(function (Post $record, PublishPost $publish): void {
                        $post = $publish->handle($record, ! $record->isPublished());

                        Notification::make()
                            ->title($post->isPublished() ? __('Update published') : __('Update unpublished'))
                            ->success()
                            ->send();
                    }),
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
