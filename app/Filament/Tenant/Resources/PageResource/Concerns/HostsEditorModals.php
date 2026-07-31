<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PageResource\Concerns;

use App\Design\TokenSelection;
use App\Enums\DesignDraftSource;
use App\Filament\Tenant\Pages\BusinessProfile;
use App\Models\Business;
use App\Site\Blocks\BlockData;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Locked;

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
     * `#[Locked]` because {@see previewDesign()} and {@see clearDesignDraft()}
     * are the only writers: this is the input
     * {@see InteractsWithPageChat::applyChatDesign()}
     * hands to `SaveDesignSelection`, so a client-writable copy would be a POST
     * straight into the `businesses` row.
     *
     * @var array<string, string|null>|null
     */
    #[Locked]
    public ?array $designDraft = null;

    /**
     * Who staged the current draft. Null whenever there is no draft.
     *
     * Also locked, and for a sharper reason than the draft itself: the browser
     * choosing its own source would let a modal draft claim to be a chat one and
     * so outlive the modal that produced it — {@see unmountAction()} below is
     * the code that decision reaches.
     */
    #[Locked]
    public ?DesignDraftSource $designDraftSource = null;

    private ?Business $businessRecord = null;

    private bool $businessLoaded = false;

    /**
     * Stage a design-token draft for the canvas preview. Nothing persists —
     * the canvas simply re-renders with the draft theme layered over the saved
     * one; "Apply to site" is still the only write path.
     *
     * Two producers now: the Design modal's live fields, and
     * {@see \App\Ai\Tools\SetSiteStyle} by way of the chat turn. Both go through
     * {@see TokenSelection::normalise()} rather than a literal key list, because
     * a literal list silently DROPS any token not named in it — the assistant
     * would describe a change the canvas never shows.
     *
     * @param  array<string, mixed>  $draft
     */
    public function previewDesign(array $draft, DesignDraftSource $source = DesignDraftSource::Modal): void
    {
        $this->designDraft = TokenSelection::normalise($draft);
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

        if ($this->designDraftSource === DesignDraftSource::Chat) {
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
