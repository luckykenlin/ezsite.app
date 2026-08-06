<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Enums\ChromeSlot;
use App\Site\Blocks\BlockData;

/**
 * What the editor canvas should render right now: the committed draft with the
 * SELECTED block's uncommitted form state substituted in.
 *
 * That substitution is why the canvas updates as the operator types without
 * anything being committed or saved — the inspector's live state exists only in
 * the component's `$data['block']` until a verb commits it, so the preview has to
 * splice it in itself.
 *
 * Pure (arrays in, arrays out) and separate from the component so the
 * substitution rule can be tested directly rather than only through a mounted
 * Livewire page — it is the one place where "what you see" and "what you would
 * save" are deliberately allowed to differ, which makes it worth pinning.
 */
final readonly class BuildEditorPreviewDraft
{
    /**
     * @param  list<array{key: string, type: string, data: array<string, mixed>}>  $blocks  the committed draft
     * @param  array<string, array{type: string, data: array<string, mixed>}|null>  $chrome  the chrome draft, per slot
     * @param  array<array-key, mixed>|null  $liveDraft  raw inspector state for the selection, if any
     * @return array{blocks: list<array{key: string, type: string, data: array<string, mixed>}>, chrome: array<string, array{type: string, data: array<string, mixed>}|null>}
     */
    public function handle(
        array $blocks,
        array $chrome,
        ?array $liveDraft,
        ?int $selectedIndex,
        ?ChromeSlot $selectedSlot,
        bool $hasBusinessProfile,
    ): array {
        // Normalised the same way a commit would, so the canvas cannot render a
        // state that saving would then change — the two used to differ here.
        $draft = $liveDraft === null ? null : BlockData::committed($liveDraft);

        if ($draft !== null && $selectedIndex !== null) {
            $blocks[$selectedIndex] = [
                'key' => $blocks[$selectedIndex]['key'],
                'type' => $blocks[$selectedIndex]['type'],
                'data' => $draft,
            ];
        }

        if ($draft !== null && $selectedSlot instanceof ChromeSlot) {
            $chrome[$selectedSlot->value] = [
                'type' => $chrome[$selectedSlot->value]['type'] ?? $selectedSlot->value,
                'data' => $draft,
            ];
        }

        // Untouched null slots render their effective default on the canvas
        // (mirroring SiteChrome's live-site fallback) without ever becoming part of
        // the draft — inspecting chrome must not materialise it into site settings.
        if ($hasBusinessProfile) {
            foreach (ChromeSlot::cases() as $case) {
                $chrome[$case->value] ??= ['type' => $case->value, 'data' => []];
            }
        }

        return ['blocks' => $blocks, 'chrome' => $chrome];
    }
}
