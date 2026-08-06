<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PageResource\Actions;

use App\Filament\Tenant\Resources\PageResource\Pages\PageEditor;
use Filament\Actions\Action;

/**
 * The only route back to the saved page.
 *
 * A restored draft carries no undo history behind it, so without this a draft
 * the operator does not want is sticky — every mount would adopt it again.
 * Hidden while there is nothing to discard, so it never reads as a way to
 * throw away the saved page itself.
 */
final readonly class DiscardDraftAction
{
    public static function make(PageEditor $editor): Action
    {
        return Action::make('discardDraft')
            ->label('Discard draft')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Throws away every unsaved change and reloads the last saved version of this page.')
            ->visible(fn (): bool => $editor->draftRestored || $editor->isDirty)
            ->action(fn () => $editor->discardDraft());
    }
}
