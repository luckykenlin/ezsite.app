<?php

declare(strict_types=1);

namespace App\Ai;

use App\Enums\ChromeSlot;
use App\Site\Blocks\BlockShape;
use Illuminate\Support\Str;

/**
 * The turn-scoped working copy of the site's header and footer — the third
 * staging draft beside {@see PageDraft} and {@see SiteStyleDraft}, and the reason
 * the assistant can finally answer "add Services to the menu".
 *
 * It needs to exist for the same reason {@see SiteStyleDraft} does: chrome is
 * SITE-scoped. It is stored in `site_settings`, rendered around every page by
 * {@see \App\Site\SiteChrome}, and therefore is not part of the block list —
 * neither {@see ChangedBlocks} nor the editor's undo snapshot can see it. A tool
 * calling {@see \App\Actions\SaveSiteChrome} from the queue worker would rewrite
 * the navigation of a live website while its owner was still reading the reply.
 *
 * So a tool mutates this instead, and what it holds rides back into the same
 * `$chrome` draft the inspector edits — `SaveSiteChrome` behind the operator's
 * Save stays the single write path. Since chrome joined the editor's undo
 * shape (E6), a turn that lands through
 * {@see \App\Filament\Tenant\Resources\PageResource\Pages\PageEditor::applyTurn()}
 * is one Undo away like everything else the assistant does.
 *
 * Mutable and therefore not `readonly`: several tools in one turn have to see
 * each other's work, exactly as with the other two drafts.
 */
final class SiteChromeDraft
{
    /**
     * Slots this turn actually changed, keyed by slot value. Empty until a tool
     * writes, which is what lets {@see toArray()} tell "left alone" from
     * "deliberately set back" — only the former must avoid handing the editor a
     * draft, since an unchanged draft would mark the site chrome dirty and ask
     * the operator to save a change nobody made.
     *
     * @var array<string, array{type: string, data: array<string, mixed>}>
     */
    private array $staged = [];

    /**
     * @param  array<string, array{type: string, data: array<string, mixed>}>  $saved  the
     *                                                                                 EFFECTIVE entry per slot — stored settings, or the default chrome.
     *                                                                                 Resolved by the caller so this stays a plain value object with no
     *                                                                                 settings lookup of its own.
     */
    public function __construct(private readonly array $saved)
    {
        //
    }

    /**
     * @return array{type: string, data: array<string, mixed>}
     */
    public function current(ChromeSlot $slot): array
    {
        return $this->staged[$slot->value]
            ?? $this->saved[$slot->value]
            ?? ['type' => $slot->value, 'data' => []];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function stage(ChromeSlot $slot, array $data): void
    {
        $this->staged[$slot->value] = ['type' => $slot->value, 'data' => $data];
    }

    /**
     * Only CHANGED slots are handed back: the editor merges rather than replaces, so
     * a turn that edited the header must not also hand back a footer it never
     * looked at — that would overwrite an operator's in-flight footer edit with a
     * stale copy read when the turn was dispatched.
     *
     * @return array<string, array{type: string, data: array<string, mixed>}>|null
     */
    public function toArray(): ?array
    {
        return $this->staged === [] ? null : $this->staged;
    }

    /**
     * The header and footer as the model should see them, one line each.
     *
     * Always both slots, whether or not this turn has touched them: the model
     * needs to know what the navigation currently holds before it can add to it,
     * and "the menu" is a thing operators refer to without naming a slot.
     */
    public function outline(): string
    {
        $lines = [];

        foreach (ChromeSlot::cases() as $slot) {
            $entry = $this->current($slot);
            $data = $entry['data'];
            $variant = $data[BlockShape::VARIANT_KEY] ?? null;
            unset($data[BlockShape::VARIANT_KEY], $data[BlockShape::BIND_KEY]);

            $lines[] = sprintf(
                '- %s%s%s',
                $slot->value,
                is_string($variant) ? ' ('.$variant.')' : '',
                $data === [] ? ' — nothing set' : "\n  ".$this->encode($data),
            );
        }

        return implode("\n", $lines);
    }

    /**
     * A slot's content, compacted the same way {@see PageDraft::encode()} does it
     * — long strings truncated so the model can recognise a value without the
     * prompt carrying a whole page of it.
     *
     * @param  array<string, mixed>  $data
     */
    private function encode(array $data): string
    {
        $compact = array_map(
            static fn (mixed $value): mixed => is_string($value) ? Str::limit($value, 120) : $value,
            $data,
        );

        return json_encode($compact, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
