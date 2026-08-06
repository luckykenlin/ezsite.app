<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Posts\Pages;

use App\Filament\Tenant\Resources\Posts\Actions\PublishPostAction;
use App\Filament\Tenant\Resources\Posts\PostResource;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

final class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    /**
     * "Visit" is deliberately absent while a post is a draft — a link to a page
     * that 404s is worse than no link.
     */
    protected function getHeaderActions(): array
    {
        return [
            // Save first, then publish: an operator who edits and hits
            // Publish means "publish what I am looking at".
            PublishPostAction::make(fn () => $this->save(shouldRedirect: false, shouldSendSavedNotification: false)),

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
