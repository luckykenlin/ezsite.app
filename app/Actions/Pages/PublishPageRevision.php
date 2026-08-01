<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Put a PAST version of a page live, without touching whatever draft is open
 * in the editor.
 *
 * The other half of "restore" ({@see \App\Filament\Tenant\Resources\PageResource\Concerns\ManagesPageVersions::restoreRevision()}):
 * restore loads a version INTO the editor for review, this ships one PAST it —
 * "roll the live site back to Tuesday" must not cost the operator the draft
 * they are halfway through.
 *
 * One transaction, because the three writes are only correct together: a
 * history row recording what is being made live, the page's blocks, and the
 * publish. The revision is written FIRST so the newest history row mirrors what
 * is now saved — the same order {@see \App\Filament\Tenant\Resources\PageResource\Pages\PageEditor::persistBlocks()}
 * keeps.
 */
final readonly class PublishPageRevision
{
    public function __construct(
        private RecordPageRevision $revisions,
        private PublishPage $publish,
    ) {
        //
    }

    public function handle(Page $page, PageRevision $revision, ?User $user = null): Page
    {
        return DB::transaction(function () use ($page, $revision, $user): Page {
            $this->revisions->handle($page, $revision->blocks, $user);

            $page->update(['blocks' => $revision->blocks]);

            return $this->publish->handle($page, true);
        });
    }
}
