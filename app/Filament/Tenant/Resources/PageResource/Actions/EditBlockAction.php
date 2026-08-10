<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PageResource\Actions;

use App\Enums\ChromeSlot;
use App\Filament\Tenant\Resources\PageResource\Pages\PageEditor;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;

/**
 * The block settings drawer: the selected block's full Filament schema
 * (images, links, variant, appearance axes) in a slide-over, opened from the
 * canvas toolbar's Edit button. Quick text edits stay on the canvas
 * (double-click); this drawer is for everything a contenteditable can't hold.
 *
 * Click-through, deliberately: no dimming overlay, no focus trap, no scroll
 * lock — the canvas stays visible AND interactive while typing, which is what
 * makes the live per-keystroke preview worth having. It also defuses the
 * objection that killed the first drawer design ("reflowed the canvas, broke
 * double-click-to-edit"): a fixed-position overlay reflows nothing.
 *
 * The modal is chrome only. The form inside is the page-bound
 * {@see PageEditor::blockForm()} rendered by the drawer view, so state stays
 * at `data.block.*` — the same path `updated()`'s canvas patch, the inline
 * canvas editor, and the draft restore all read. A real `->schema()` on this
 * action would move state into `mountedActions.*.data` and break all four.
 * No submit either: commits stay lazy through `commitSelectedBlock()` on
 * selection change and save, exactly as the old inspector column behaved.
 */
final readonly class EditBlockAction
{
    public static function make(PageEditor $editor): Action
    {
        return Action::make('editBlock')
            ->slideOver()
            ->modalClickThrough()
            ->modalWidth(Width::Medium)
            ->modalHeading(function () use ($editor): string {
                $type = $editor->selectedBlock()['type'] ?? '';

                return $type === '' ? 'Block' : Str::headline($type);
            })
            ->modalDescription(fn (): ?string => $editor->chromeSlot($editor->selectedBlockKey) instanceof ChromeSlot
                ? 'Shown on every page'
                : null)
            ->modalContent(fn (): View => view(
                'filament.tenant.pages.partials.page-editor-block-drawer',
                ['editor' => $editor],
            ))
            ->modalSubmitAction(false)
            ->modalCancelAction(fn (Action $action): Action => $action->label('Done'))
            ->extraModalWindowAttributes(['class' => 'pe-block-drawer']);
    }
}
