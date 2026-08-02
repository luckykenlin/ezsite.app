<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Posts\Pages;

use App\Actions\Posts\PublishPost;
use App\Filament\Tenant\Resources\Posts\PostResource;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

final class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    /**
     * One button with two faces, the shape {@see \App\Filament\Tenant\Resources\PageResource\Actions\PublishPageAction}
     * established: every label, colour and icon is a closure over the current
     * record, so publishing flips the button without remounting the page.
     *
     * "Visit" is deliberately absent while a post is a draft — a link to a page
     * that 404s is worse than no link.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')
                ->label(fn (): string => $this->post()->isPublished() ? __('Unpublish') : __('Publish'))
                ->icon(fn (): Heroicon => $this->post()->isPublished() ? Heroicon::OutlinedEyeSlash : Heroicon::OutlinedRocketLaunch)
                ->color(fn (): string => $this->post()->isPublished() ? 'gray' : 'primary')
                ->requiresConfirmation(fn (): bool => $this->post()->isPublished())
                ->modalHeading(__('Take this update off your site?'))
                ->modalDescription(__('Its page will start returning "not found" to anyone who already has the link.'))
                ->action(function (PublishPost $publish): void {
                    // Save first, then publish: an operator who edits and hits
                    // Publish means "publish what I am looking at".
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);

                    $post = $publish->handle($this->post(), ! $this->post()->isPublished());

                    Notification::make()
                        ->title($post->isPublished() ? __('Update published') : __('Update unpublished'))
                        ->success()
                        ->send();
                }),

            Action::make('visit')
                ->label(__('View on your site'))
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (): string => $this->post()->getUrl(), shouldOpenInNewTab: true)
                ->visible(fn (): bool => $this->post()->isPublished()),

            DeleteAction::make(),
        ];
    }

    private function post(): Post
    {
        /** @var Post $post */
        $post = $this->getRecord();

        return $post;
    }
}
