<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Posts\Actions;

use App\Actions\Posts\PublishPost;
use App\Models\Post;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Publish or unpublish an update, from the table row or the composer header —
 * the same one-button-two-faces shape {@see \App\Filament\Tenant\Resources\PageResource\Actions\PublishPageAction}
 * established: every label, colour and icon is a closure over the current
 * record, so publishing flips the button without a remount.
 *
 * The two call sites keep their documented asymmetry through `$beforePublish`:
 * the table publishes the LAST SAVED content, while the composer saves first —
 * an operator who edits and hits Publish means "publish what I am looking at".
 */
final readonly class PublishPostAction
{
    public static function make(?Closure $beforePublish = null): Action
    {
        return Action::make('publish')
            ->label(fn (Post $record): string => $record->isPublished() ? __('Unpublish') : __('Publish'))
            ->icon(fn (Post $record): Heroicon => $record->isPublished() ? Heroicon::OutlinedEyeSlash : Heroicon::OutlinedRocketLaunch)
            ->color(fn (Post $record): string => $record->isPublished() ? 'gray' : 'primary')
            ->requiresConfirmation(fn (Post $record): bool => $record->isPublished())
            ->modalHeading(__('Take this update off your site?'))
            ->modalDescription(__('Its page will start returning "not found" to anyone who already has the link.'))
            ->action(function (Post $record, PublishPost $publish) use ($beforePublish): void {
                if ($beforePublish instanceof Closure) {
                    $beforePublish();
                }

                $post = $publish->handle($record, ! $record->isPublished());

                Notification::make()
                    ->title($post->isPublished() ? __('Update published') : __('Update unpublished'))
                    ->success()
                    ->send();
            });
    }
}
