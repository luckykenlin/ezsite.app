<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PageResource\Concerns;

use App\Enums\ChromeSlot;
use App\Models\SiteSetting;
use App\Site\Blocks\BlockData;
use App\Site\Blocks\BlockShape;
use App\Site\Blocks\BlockVocabulary;

/**
 * The site-wide header/footer draft the editor edits alongside page blocks.
 *
 * Chrome is stored per SITE (`site_settings`) and rendered around every page by
 * {@see \App\Site\SiteChrome}, but the editor lets you edit it in place through
 * {@see ChromeSlot}-keyed pseudo block keys (`chrome:header`), so the same
 * inspector serves both. That indirection is the whole reason this is a separate
 * concern: it shares the commit pipeline (and, since E6, the undo snapshot —
 * see {@see HasBlockHistory::currentSnapshot()}) with page blocks, but none of
 * their storage.
 *
 * A null slot means "the tenant relies on the default chrome" and STAYS null on
 * save — merely opening the editor must never materialise a default into site
 * settings.
 *
 * Expects the host to provide `$selectedBlockKey`.
 */
trait HasSiteChromeDraft
{
    /**
     * The site-wide header/footer DRAFT entries, hydrated from the effective
     * chrome (saved settings, or the default when a Business exists). Edited
     * through the same commit pipeline as page blocks; persisted via
     * SaveSiteChrome only when actually changed. Rides in the undo snapshot
     * alongside blocks and the design draft.
     *
     * @var array<string, array{type: string, data: array<string, mixed>}|null>
     */
    public array $chrome = ['header' => null, 'footer' => null];

    public bool $chromeDirty = false;

    /**
     * Land the header/footer a chat turn staged. Returns whether anything
     * actually moved, so {@see \App\Filament\Tenant\Resources\PageResource\Pages\PageEditor::applyTurn()}
     * can enable Save and repaint the canvas for a chrome-only turn — without
     * it, "add Services to the menu" changed state the operator could neither
     * see nor save.
     *
     * Merges per slot rather than replacing the pair, because
     * {@see \App\Ai\SiteChromeDraft::toArray()} returns only the slots the turn
     * actually touched: a turn that edited the header must not overwrite a footer
     * the operator was editing by hand while it ran.
     *
     * Does not snapshot itself: the one caller is applyTurn(), whose single
     * turn-wide snapshot already carries the pre-turn chrome (chrome joined the
     * undo shape in E6 — see {@see HasBlockHistory::currentSnapshot()}).
     *
     * @param  array<string, array{type: string, data: array<string, mixed>}>  $chrome
     */
    public function applyChromeDraft(array $chrome): bool
    {
        $changed = false;

        foreach (ChromeSlot::cases() as $slot) {
            $entry = $chrome[$slot->value] ?? null;

            if ($entry === null) {
                continue;
            }

            $committed = [
                'type' => $slot->value,
                // Pruned like every other commit into this draft, so an untouched
                // slot compares identical to its stored form and the canvas can
                // skip a reload — see BlockData::committed().
                'data' => BlockData::committed($entry['data']),
            ];

            // Identical means untouched: a staged copy that matches the draft
            // must not flag a save the operator has nothing to review.
            if (($this->chrome[$slot->value] ?? null) === $committed) {
                continue;
            }

            $this->chrome[$slot->value] = $committed;
            $this->chromeDirty = true;
            $changed = true;
        }

        return $changed;
    }

    /**
     * The chrome slot a pseudo selection key refers to, or null for regular
     * page-block keys.
     */
    public function chromeSlot(?string $key): ?ChromeSlot
    {
        return ChromeSlot::fromEditorKey($key);
    }

    /**
     * A slot's draft in SaveSiteChrome's shape: a single-entry list, or null
     * when the tenant still relies on the default chrome.
     *
     * @return array<int, array{type: string, data: array<string, mixed>}>|null
     */
    private function chromeEntriesToSave(ChromeSlot $slot): ?array
    {
        $entry = $this->chrome[$slot->value] ?? null;

        return $entry === null ? null : [$entry];
    }

    /**
     * The whole chrome draft, hydrated from stored site settings — what both
     * "open the editor" and "discard the draft" start from.
     *
     * Derived from {@see ChromeSlot::cases()} rather than written out per slot,
     * so a third slot is one enum case rather than an edit in two files that
     * must not drift.
     *
     * @return array<string, array{type: string, data: array<string, mixed>}|null>
     */
    private function hydratedChrome(): array
    {
        // Read once and passed down, rather than once per slot: the row is the
        // same for both, and this runs on every mount.
        $settings = SiteSetting::query()->first();

        $chrome = [];

        foreach (ChromeSlot::cases() as $slot) {
            $chrome[$slot->value] = $this->hydratedChromeSlot($slot, $settings);
        }

        return $chrome;
    }

    /**
     * The STORED chrome entry for a slot, or null when the tenant relies on
     * the default chrome — a null slot stays null on save, so merely opening
     * the editor never materializes the default into site settings. The
     * canvas preview computes the effective default at push time instead.
     *
     * @return array{type: string, data: array<string, mixed>}|null
     */
    private function hydratedChromeSlot(ChromeSlot $slot, ?SiteSetting $settings): ?array
    {
        $stored = $slot === ChromeSlot::Header ? $settings?->header : $settings?->footer;
        $entry = is_array($stored) ? ($stored[0] ?? null) : null;

        if (! is_array($entry)) {
            return null;
        }

        $type = $entry['type'] ?? null;
        $data = $entry['data'] ?? null;

        return [
            'type' => is_string($type) ? $type : $slot->value,
            'data' => is_array($data) ? BlockData::stringKeyed($data) : [],
        ];
    }

    /**
     * A fresh draft entry for an empty chrome slot, pre-filled with the
     * block's default variant so an inspect-without-editing visit commits
     * identical and never flags the chrome dirty.
     *
     * @return array{type: string, data: array<string, mixed>}
     */
    private function defaultChromeEntry(ChromeSlot $slot): array
    {
        $variant = resolve(BlockVocabulary::class)->get($slot->value)?->defaultVariant();

        return [
            'type' => $slot->value,
            'data' => $variant === null ? [] : [BlockShape::VARIANT_KEY => $variant],
        ];
    }
}
