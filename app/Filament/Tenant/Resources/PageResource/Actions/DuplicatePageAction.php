<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PageResource\Actions;

use App\Actions\Pages\DuplicatePage;
use App\Filament\Tenant\Resources\PageResource;
use App\Filament\Tenant\Resources\PageResource\Pages\PageEditor;
use Filament\Actions\Action;

/**
 * Copy this page as a new draft and open it.
 *
 * Duplicates the LAST SAVED version, not the canvas: the copy is written to the
 * database, and an unsaved draft is by definition work the operator has not
 * committed to this page — let alone to a second one. The modal says so, since
 * the difference only shows up when there is a draft open.
 */
final readonly class DuplicatePageAction
{
    public static function make(PageEditor $editor): Action
    {
        return Action::make('duplicatePage')
            ->label('Duplicate page')
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('Duplicates the last saved version as a new draft page.')
            ->action(function () use ($editor): void {
                $copy = resolve(DuplicatePage::class)->handle($editor->pageRecord());

                $editor->redirect(PageResource::getUrl('edit', ['record' => $copy]), navigate: true);
            });
    }
}
