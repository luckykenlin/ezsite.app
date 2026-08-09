<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PageResource\Concerns;

use App\Enums\DesignDraftSource;
use App\Filament\Tenant\Concerns\EditsSiteStyles;
use App\Models\Business;

/**
 * The editor's half of the Site Styles panel: the inspector column's second
 * face, and the canvas preview behind it.
 *
 * The panel itself — groups, specimens, staging rules, the single write path —
 * is {@see EditsSiteStyles}, shared with {@see \App\Filament\Tenant\Pages\Design}.
 * What is here is only what the EDITOR adds: a staged selection is pushed
 * straight onto the canvas, so a look is judged against the operator's real page
 * rather than imagined from a name.
 *
 * That replaced a modal of nine `Select`s whose options were token enum values
 * run through `Str::headline()` — "Refined", "Serene", "Quiet" — over a canvas
 * the modal was covering.
 *
 * Staging is not saving, and a PERSISTENT panel makes that matter more, not
 * less: there is no modal close to bound a mistake. The rule predates this rail
 * (a stray click used to restyle the whole live site with no undo) and
 * "Apply to site" is still the only write.
 *
 * Expects the host to provide the {@see HostsEditorModals} members
 * (`previewDesign()`, `clearDesignDraft()`, `businessOrFail()`, `$designDraft`).
 */
trait HostsStyleRail
{
    use EditsSiteStyles;

    /**
     * Whether the inspector column is showing Site Styles instead of the page
     * and block panels.
     */
    public bool $showingSiteStyles = false;

    /**
     * Design tokens live on the Business row, so without one there is nothing to
     * show and {@see EditsSiteStyles::styleSelection()} would throw. The tab is
     * hidden in that case; this guard is the server-side half, for a click that
     * arrives anyway.
     */
    public function showSiteStyles(): void
    {
        $this->showingSiteStyles = $this->hasBusinessProfile();
    }

    public function showPageInspector(): void
    {
        $this->showingSiteStyles = false;
        $this->styleGroup = null;
    }

    public function styleBusiness(): Business
    {
        return $this->businessOrFail();
    }

    /**
     * @return array<string, string|null>|null
     */
    protected function stagedStyles(): ?array
    {
        return $this->designDraft;
    }

    /**
     * The canvas draft IS this surface's staging store — one selection, not a
     * copy kept in step with one. `Rail` rather than the chat source because the
     * two are treated differently on reload: a chat draft is an unanswered
     * question worth restoring, a rail draft belongs to the pane in front of you.
     *
     * @param  array<string, string|null>|null  $selection
     */
    protected function stageStyles(?array $selection): void
    {
        if ($selection === null) {
            $this->clearDesignDraft();

            return;
        }

        $this->previewDesign($selection, DesignDraftSource::Rail);
    }
}
