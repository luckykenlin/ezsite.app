<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PageResource\Actions;

use App\Filament\Tenant\Resources\PageResource\Pages\PageEditor;
use Filament\Actions\Action;

/**
 * Publish or unpublish, from inside the editor.
 *
 * One button with two faces rather than two buttons, because the page is only
 * ever in one of the two states and a disabled twin would say nothing the label
 * does not. Every face is a closure over the CURRENT record: the state flips
 * without a remount ({@see PageEditor::togglePublish()}), so a value evaluated
 * once at build time would leave the button describing the previous state.
 *
 * Publishing saves the open draft first — "publish what you see" — which is why
 * the confirmation says so.
 */
final readonly class PublishPageAction
{
    public static function make(PageEditor $editor): Action
    {
        return Action::make('publish')
            ->label(fn (): string => $editor->pageRecord()->isDraft() ? 'Publish' : 'Unpublish')
            ->color(fn (): string => $editor->pageRecord()->isDraft() ? 'success' : 'gray')
            ->requiresConfirmation()
            ->modalDescription(fn (): string => $editor->pageRecord()->isDraft()
                ? 'The current draft is saved and the page goes live.'
                : 'The page returns to draft and disappears from the live site.')
            ->action(fn () => $editor->togglePublish());
    }
}
