<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Design\TokenSelection;
use App\Enums\ChromeSlot;
use App\Models\Page;
use App\Site\Blocks\BlockData;
use Carbon\CarbonImmutable;

/**
 * Read back the editor's unsaved working state, normalised rather than trusted.
 *
 * Same reasoning as {@see CacheBlockHistory::read()} and {@see CacheChatTurn::read()}:
 * this value was written by some earlier version of this app, it flows straight
 * into the component's `$blocks`, and from there into `pages.blocks` on the next
 * Save. A malformed entry is dropped here rather than downstream.
 *
 * Returns null when there is nothing to restore, so a caller can distinguish "no
 * draft" from "an empty draft" — the difference between leaving the editor alone
 * and telling the operator their work came back.
 */
final readonly class ReadPageEditorDraft
{
    /**
     * @return array{blocks: list<array{key: string, type: string, data: array<string, mixed>}>, chrome: array<string, array{type: string, data: array<string, mixed>}|null>, chrome_dirty: bool, selected_block_key: string|null, inspector: array<string, mixed>|null, sample_hint_shown: bool, chat_edit_awaiting_save: bool, chat_turn: array{token: string, started_at: int}|null, design: array<string, string|null>|null, saved_at: CarbonImmutable|null}|null
     */
    public function handle(Page $page): ?array
    {
        $draft = $page->draft;

        if ($draft === null || $draft === []) {
            return null;
        }

        $selected = $draft['selected_block_key'] ?? null;
        $inspector = $draft['inspector'] ?? null;

        return [
            'blocks' => $this->blocks($draft['blocks'] ?? null),
            'chrome' => $this->chrome($draft['chrome'] ?? null),
            'chrome_dirty' => (bool) ($draft['chrome_dirty'] ?? false),
            'selected_block_key' => is_string($selected) ? $selected : null,
            'inspector' => is_array($inspector) ? BlockData::stringKeyed($inspector) : null,
            'sample_hint_shown' => (bool) ($draft['sample_hint_shown'] ?? false),
            'chat_edit_awaiting_save' => (bool) ($draft['chat_edit_awaiting_save'] ?? false),
            'chat_turn' => $this->chatTurn($draft['chat_turn'] ?? null),
            // Only ever a CHAT-staged style — see the writer for why the modal's
            // never reaches storage. Normalised like everything else here: it goes
            // into `$designDraft` and from there into the canvas preview.
            'design' => TokenSelection::normalise($draft['design'] ?? null),
            'saved_at' => $page->draft_updated_at,
        ];
    }

    /**
     * @return list<array{key: string, type: string, data: array<string, mixed>}>
     */
    private function blocks(mixed $blocks): array
    {
        if (! is_array($blocks)) {
            return [];
        }

        $normalised = [];

        foreach ($blocks as $block) {
            if (! $this->isBlock($block)) {
                continue;
            }

            $data = $block['data'] ?? null;

            $normalised[] = [
                'key' => $block['key'],
                'type' => $block['type'],
                'data' => BlockData::stringKeyed(is_array($data) ? $data : []),
            ];
        }

        return $normalised;
    }

    /**
     * The chrome draft, per slot.
     *
     * A slot is legitimately null — it means "this tenant relies on the default
     * header/footer", and {@see \App\Filament\Tenant\Resources\PageResource\Concerns\HasSiteChromeDraft}
     * is explicit that merely opening the editor must never materialise that
     * default into site settings. So null is preserved, never defaulted, and only
     * the two known slots are ever returned.
     *
     * @return array<string, array{type: string, data: array<string, mixed>}|null>
     */
    private function chrome(mixed $chrome): array
    {
        $stored = is_array($chrome) ? $chrome : [];
        $normalised = [];

        foreach (ChromeSlot::cases() as $slot) {
            $entry = $stored[$slot->value] ?? null;
            $type = is_array($entry) ? ($entry['type'] ?? null) : null;

            if (! is_array($entry) || ! is_string($type)) {
                $normalised[$slot->value] = null;

                continue;
            }

            $data = $entry['data'] ?? null;

            $normalised[$slot->value] = [
                'type' => $type,
                'data' => BlockData::stringKeyed(is_array($data) ? $data : []),
            ];
        }

        return $normalised;
    }

    /**
     * The pointer to a turn that was in flight when the draft was written.
     *
     * This is what makes a chat turn survive a reload: the turn's RESULT is already
     * durable in the cache, and only its address — the token — used to die with the
     * component.
     *
     * @return array{token: string, started_at: int}|null
     */
    private function chatTurn(mixed $turn): ?array
    {
        if (! is_array($turn)) {
            return null;
        }

        $token = $turn['token'] ?? null;
        $startedAt = $turn['started_at'] ?? null;

        if (! is_string($token) || $token === '' || ! is_int($startedAt)) {
            return null;
        }

        return ['token' => $token, 'started_at' => $startedAt];
    }

    /**
     * @phpstan-assert-if-true array{key: string, type: string, data?: mixed} $block
     */
    private function isBlock(mixed $block): bool
    {
        return is_array($block)
            && is_string($block['key'] ?? null)
            && is_string($block['type'] ?? null);
    }
}
