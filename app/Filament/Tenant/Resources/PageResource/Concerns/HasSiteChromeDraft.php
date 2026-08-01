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
 * concern: it shares the commit pipeline with page blocks but none of their
 * storage, and it is deliberately excluded from the undo stack.
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
     * SaveSiteChrome only when actually changed. Excluded from the undo
     * stack (structure-level history covers page blocks only).
     *
     * @var array<string, array{type: string, data: array<string, mixed>}|null>
     */
    public array $chrome = ['header' => null, 'footer' => null];

    public bool $chromeDirty = false;

    /**
     * Land the header/footer a chat turn staged.
     *
     * Merges per slot rather than replacing the pair, because
     * {@see \App\Ai\SiteChromeDraft::toArray()} returns only the slots the turn
     * actually touched: a turn that edited the header must not overwrite a footer
     * the operator was editing by hand while it ran.
     *
     * Marks the chrome dirty but does NOT snapshot, which is the one place an AI
     * chrome edit differs from an AI block edit. Chrome has never been on the undo
     * stack — structure-level history covers page blocks only — so making just this
     * path undoable would give one piece of state two histories. It stays as
     * reversible as a hand edit: visible on the canvas, and unsaved until Save.
     *
     * @param  array<string, array{type: string, data: array<string, mixed>}>  $chrome
     */
    public function applyChromeDraft(array $chrome): void
    {
        foreach (ChromeSlot::cases() as $slot) {
            $entry = $chrome[$slot->value] ?? null;

            if ($entry === null) {
                continue;
            }

            $this->chrome[$slot->value] = [
                'type' => $slot->value,
                // Pruned like every other commit into this draft, so an untouched
                // slot compares identical to its stored form and the canvas can
                // skip a reload — see BlockData::committed().
                'data' => BlockData::committed($entry['data']),
            ];

            $this->chromeDirty = true;
        }
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
     * The STORED chrome entry for a slot, or null when the tenant relies on
     * the default chrome — a null slot stays null on save, so merely opening
     * the editor never materializes the default into site settings. The
     * canvas preview computes the effective default at push time instead.
     *
     * @return array{type: string, data: array<string, mixed>}|null
     */
    private function hydratedChromeSlot(ChromeSlot $slot): ?array
    {
        $settings = SiteSetting::query()->first();
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
