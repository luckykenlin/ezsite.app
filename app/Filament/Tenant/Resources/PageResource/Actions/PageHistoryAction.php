<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PageResource\Actions;

use App\Filament\Tenant\Resources\PageResource\Pages\PageEditor;
use App\Models\PageRevision;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * The in-editor version history: every save of this page, newest first, with a
 * one-click restore.
 *
 * `PageEditor::persistBlocks()` is a destructive in-place update, so before this
 * a bad Save — an accidental delete, an assistant rewrite approved too quickly —
 * had no route back. The draft column covers everything before a Save; this covers
 * everything after.
 *
 * Restoring does NOT write to the page. It loads the chosen version into the
 * editor as an unsaved draft ({@see PageEditor::restoreRevision()}), so it lands
 * on the undo stack, repaints the canvas and still needs an explicit Save. Picking
 * the wrong version is therefore one Undo away, and the operator reviews it before
 * it goes anywhere — the same contract every other edit here follows.
 *
 * A Radio rather than a table: the list is bounded at
 * {@see \App\Actions\Pages\RecordPageRevision::LIMIT} and picking one of thirty
 * dated options is a choice, not a dataset to filter.
 */
final readonly class PageHistoryAction
{
    public static function make(PageEditor $editor): Action
    {
        return Action::make('pageHistory')
            ->label('Version history')
            ->color('gray')
            ->icon(Heroicon::OutlinedClock)
            ->modalHeading('Version history')
            ->modalDescription('Each save of this page. Restoring loads that version onto the canvas as an unsaved change — review it, then Save. Publishing puts it live directly, leaving the canvas alone.')
            ->modalSubmitActionLabel('Restore')
            // Nothing to show until the page has been saved at least once.
            ->visible(fn (): bool => self::options($editor) !== [])
            ->schema([
                Radio::make('revision')
                    ->label('Versions')
                    ->options(fn (): array => self::options($editor))
                    ->required(),
                TextInput::make('label')
                    ->label('Name the selected version')
                    ->placeholder(__('e.g. Launch version'))
                    ->helperText(__('Optional. A named version is never pruned from this list; leave empty and press "Name version" to un-name one.'))
                    ->maxLength(60),
            ])
            // Three verbs on one picked version. Restore is the default submit;
            // the footer actions reach the SAME action closure with their own
            // argument flag — Filament's documented pattern for a modal with
            // several outcomes over one form.
            ->extraModalFooterActions(fn (Action $action): array => [
                $action->makeModalSubmitAction('nameVersion', arguments: ['name' => true])
                    ->label(__('Name version'))
                    ->color('gray'),
                $action->makeModalSubmitAction('publishVersion', arguments: ['publish' => true])
                    ->label(__('Publish this version'))
                    ->color('warning'),
            ])
            ->action(function (array $data, array $arguments) use ($editor): void {
                $revision = $data['revision'] ?? null;

                // The Radio is ->required() and its options are ids, but the
                // submitted value is still untrusted form state.
                if (is_numeric($revision)) {
                    $label = $data['label'] ?? '';

                    match (true) {
                        (bool) ($arguments['name'] ?? false) => $editor->nameRevision((int) $revision, is_string($label) ? $label : ''),
                        (bool) ($arguments['publish'] ?? false) => $editor->publishRevision((int) $revision),
                        default => $editor->restoreRevision((int) $revision),
                    };
                }
            });
    }

    /**
     * Revision id => a label the operator can actually choose between: when it was
     * saved, how long ago, who saved it, and how many blocks it held — the last of
     * those being what makes an accidental delete obvious in the list.
     *
     * @return array<int, string>
     */
    private static function options(PageEditor $editor): array
    {
        return PageRevision::query()
            ->with('user')
            ->where('page_id', $editor->pageRecord()->id)
            ->orderByDesc('id')
            ->get()
            ->mapWithKeys(fn (PageRevision $revision): array => [
                $revision->id => self::label($revision),
            ])
            ->all();
    }

    private static function label(PageRevision $revision): string
    {
        $saved = $revision->created_at;

        $line = sprintf(
            '%s — %s (%s)',
            $saved === null ? __('Unknown time') : $saved->format('j M Y, H:i'),
            trans_choice('{0} empty page|{1} :count block|[2,*] :count blocks', $revision->blockCount(), [
                'count' => $revision->blockCount(),
            ]),
            $revision->user->name ?? __('unknown'),
        );

        // The operator's name leads — it is what they will scan for.
        return $revision->label === null ? $line : sprintf('★ %s — %s', $revision->label, $line);
    }
}
