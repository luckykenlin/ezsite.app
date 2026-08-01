<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PageResource\Actions;

use App\Filament\Tenant\Resources\PageResource\Pages\PageEditor;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\URL;

/**
 * A stakeholder link to the SAVED page, draft status included — signed and
 * expiring, so it needs no account and dies on its own.
 *
 * The copy itself happens in the browser (`editor.ts` listens for
 * `page-editor:copy-link`), which is why this dispatches rather than notifying
 * with a URL the operator would have to select and copy by hand.
 */
final readonly class SharePreviewAction
{
    /**
     * How long a shared link stays good. Long enough to survive a weekend of
     * stakeholder review, short enough that a link pasted into a chat thread
     * does not outlive the draft it was showing.
     */
    private const int VALID_DAYS = 7;

    public static function make(PageEditor $editor): Action
    {
        return Action::make('sharePreview')
            ->label('Share preview')
            ->color('gray')
            ->icon(Heroicon::OutlinedLink)
            ->action(function () use ($editor): void {
                // Signed relative (matching the route's `signed:relative`),
                // then absolutised against the host the operator is on — so the
                // link survives a later move to a custom domain.
                $editor->dispatch('page-editor:copy-link', url: url(URL::temporarySignedRoute(
                    'page.shared-preview',
                    now()->addDays(self::VALID_DAYS),
                    ['page' => $editor->pageRecord()->id],
                    absolute: false,
                )));

                Notification::make()
                    ->title(__('Preview link copied'))
                    ->body(__('Anyone with the link can view the saved page for :days days — no account needed.', [
                        'days' => self::VALID_DAYS,
                    ]))
                    ->success()
                    ->send();
            });
    }
}
