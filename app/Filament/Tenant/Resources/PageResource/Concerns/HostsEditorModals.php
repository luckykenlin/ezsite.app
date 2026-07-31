<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PageResource\Concerns;

use App\Design\TokenKey;
use App\Filament\Tenant\Pages\BusinessProfile;
use App\Models\Business;
use App\Site\Blocks\BlockData;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * The callbacks the editor's modal builders reach back into.
 *
 * {@see \App\Filament\Tenant\Resources\PageResource\Actions\DesignAction} and
 * {@see \App\Filament\Tenant\Resources\PageResource\Actions\PageSettingsAction}
 * are built as `static make(PageEditor $editor)` (a documented convention in
 * CLAUDE.md), so they close over the concrete page and call it back. That is why
 * these members are public — they are an API for exactly two callers, not for the
 * blade — and grouping them here says so in one place instead of leaving them
 * scattered through the block/undo state machine.
 *
 * No `EditorHost` interface: the convention pins the parameter type to the
 * concrete class, and an interface method is public by definition, so it would
 * make these no less public while adding a second thing to keep in step.
 *
 * The business record is memoized on the component rather than read through
 * `BindResolver` because the editor mutates it (the Design modal writes tokens)
 * and must see its own writes within the request.
 *
 * Expects the host to provide `pageRecord()` and `pushPreview()`.
 */
trait HostsEditorModals
{
    /**
     * Unsaved design-token values previewed on the canvas only (set while
     * the Design modal is open; cleared on apply or close). Never persisted
     * by Save — "Apply to site" in the modal is the only write path.
     *
     * @var array<string, string|null>|null
     */
    public ?array $designDraft = null;

    /**
     * Who staged the current draft: `'modal'` for the Design modal's live
     * fields, `'chat'` for the assistant. Null whenever there is no draft.
     *
     * The distinction is load-bearing, not bookkeeping. {@see unmountAction()}
     * discards the draft on ANY modal close, which is right for the modal's own
     * transient state and catastrophic for the assistant's: opening Page
     * settings would silently throw away a restyle the operator was still
     * reviewing, with nothing on screen to say it had gone.
     */
    public ?string $designDraftSource = null;

    private ?Business $businessRecord = null;

    private bool $businessLoaded = false;

    /**
     * Stage a design-token draft for the canvas preview. Nothing persists —
     * the canvas simply re-renders with the draft theme layered over the saved
     * one; "Apply to site" is still the only write path.
     *
     * Two producers now: the Design modal's live fields, and
     * {@see \App\Ai\Tools\SetSiteStyle} by way of the chat turn. The key list
     * is derived from {@see TokenKey} rather than written out, because a
     * literal list silently DROPS any token not named in it — the assistant
     * would describe a change the canvas never shows.
     *
     * @param  array<string, mixed>  $draft
     * @param  'modal'|'chat'  $source
     */
    public function previewDesign(array $draft, string $source = 'modal'): void
    {
        $staged = ['preset' => is_string($draft['preset'] ?? null) ? $draft['preset'] : null];

        foreach (TokenKey::values() as $key) {
            $staged[$key] = is_string($draft[$key] ?? null) ? $draft[$key] : null;
        }

        $this->designDraft = $staged;
        $this->designDraftSource = $source;

        $this->pushPreview();
    }

    public function clearDesignDraft(): void
    {
        if ($this->designDraft === null) {
            return;
        }

        $this->designDraft = null;
        $this->designDraftSource = null;
        $this->pushPreview();
    }

    /**
     * Closing a modal without applying discards the draft the MODAL staged —
     * its fields are gone, so the preview they were driving should be too.
     *
     * A chat-staged draft survives: it is the result of a turn the operator has
     * not answered yet, and it has its own Apply affordance in the chat rail.
     */
    public function unmountAction(bool|string|null $cancelParentActions = null): void
    {
        parent::unmountAction($cancelParentActions);

        if ($this->designDraftSource === 'chat') {
            return;
        }

        $this->clearDesignDraft();
    }

    /**
     * The tenant's Business, read once per Livewire request. Memoized on the
     * component (not through the request-scoped BindResolver) because the
     * Design modal WRITES the business: a cache shared with the render layer
     * would have to be invalidated on save, and a stale read here would show
     * the operator their pre-save tokens.
     */
    public function business(): ?Business
    {
        if (! $this->businessLoaded) {
            $this->businessRecord = Business::query()->first();
            $this->businessLoaded = true;
        }

        return $this->businessRecord;
    }

    /**
     * The Business, for the paths only reachable once it exists (the Design
     * modal is hidden without one).
     */
    public function businessOrFail(): Business
    {
        $business = $this->business();

        if (! $business instanceof Business) {
            throw (new ModelNotFoundException)->setModel(Business::class);
        }

        return $business;
    }

    public function hasBusinessProfile(): bool
    {
        return $this->business() instanceof Business;
    }

    public function businessProfileUrl(): string
    {
        return BusinessProfile::getUrl();
    }

    /**
     * Persist the page-settings modal and repaint: a layout or title change
     * re-renders the whole canvas document.
     *
     * @param  array<array-key, mixed>  $settings
     */
    public function updatePageSettings(array $settings): void
    {
        $this->pageRecord()->update(BlockData::stringKeyed($settings));

        $this->refreshCanvas();
    }

    /**
     * Re-publish the draft and reload the canvas — the public repaint hook
     * for collaborators that changed something the canvas renders.
     */
    public function refreshCanvas(): void
    {
        $this->pushPreview();
    }
}
