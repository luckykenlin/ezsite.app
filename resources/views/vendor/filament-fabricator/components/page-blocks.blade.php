{{--
    Overrides Z3d0X\FilamentFabricator's block render loop.

    The stock loop skips unknown block types *silently* and errors outright on a
    structurally malformed entry (missing `type`, non-array `data`). On a live,
    single-DB multi-tenant site an AI- or import-produced bad block would then
    500 the whole page. This version delegates resolution to BlockRegistry and
    degrades gracefully: any block it cannot render is skipped and logged, never
    fatal. Rendering stays escaped (`{{ }}`) — block views forbid `{!! !!}`.

    Editor mode: the page editor's canvas passes `editorKeys` (one transient
    key per block, parallel by index). Each rendered block is then wrapped in a
    `data-block-key` div for click-to-select, and blocks the live site would
    skip render a visible placeholder instead (selectable, so they stay
    deletable from the editor) — without the warning logs, since the
    placeholder itself surfaces the problem on every canvas refresh. With
    `editorKeys` null (every live-site render), output is unchanged.
--}}
{{-- page defaults to null so the loop also renders standalone (the editor's
     single-block patch fragments have no layout ancestor to inherit from).
     `insertable` (editor mode, page blocks only) adds hover "+" dividers
     between blocks that arm the editor's insertion point. --}}
@aware(['page' => null])
@props(['blocks' => [], 'editorKeys' => null, 'insertable' => false])

@php
    // Preload related data per type, guarded so a malformed/unknown entry can't
    // break the pass. Groups hold references so preloadRelatedData() mutations
    // (e.g. future bind hydration) propagate into what we render below.
    $groups = [];
    foreach ($blocks as $groupIndex => &$groupBlock) {
        if (! is_array($groupBlock) || ! isset($groupBlock['type']) || ! is_string($groupBlock['type'])) {
            continue;
        }
        $groups[$groupBlock['type']][] = &$groupBlock;
    }
    unset($groupBlock);

    foreach ($groups as $groupType => $group) {
        $groupClass = \Z3d0X\FilamentFabricator\Facades\FilamentFabricator::getPageBlockFromName($groupType);
        if ($groupClass !== null && $page !== null) {
            $groupClass::preloadRelatedData($page, $group);
        }
    }
@endphp

@foreach ($blocks as $blockIndex => $block)
    @if ($insertable && is_array($editorKeys))
        <button type="button" data-editor-insert="{{ $blockIndex }}" title="Insert a block here"><span>＋ Add block</span></button>
    @endif

    @php
        $editorKey = is_array($editorKeys) ? ($editorKeys[$blockIndex] ?? null) : null;

        $component = is_array($block)
            ? \App\Filament\Fabricator\BlockRegistry::resolveComponent($block)
            : null;

        if ($editorKey === null) {
            if (! is_array($block)) {
                \Illuminate\Support\Facades\Log::warning('fabricator.block_skipped', [
                    'reason' => 'not_an_array',
                    'index' => $blockIndex,
                ]);
            } elseif ($component === null) {
                \Illuminate\Support\Facades\Log::warning('fabricator.block_skipped', [
                    'reason' => 'unresolved',
                    'type' => $block['type'] ?? null,
                    'index' => $blockIndex,
                ]);
            }
        }
    @endphp

    @if ($component !== null)
        @php
            $blockClass = \Z3d0X\FilamentFabricator\Facades\FilamentFabricator::getPageBlockFromName($block['type']);
            $blockData = \App\Filament\Fabricator\BlockRegistry::normalizeData($block);
            $bindAttributes = \App\Filament\Fabricator\BlockRegistry::bindAttributes($block);

            if ($bindAttributes === null && $editorKey === null) {
                \Illuminate\Support\Facades\Log::warning('fabricator.block_skipped', [
                    'reason' => 'unresolved_bind',
                    'type' => $block['type'],
                    'index' => $blockIndex,
                ]);
            }
        @endphp

        @if ($bindAttributes !== null)
            @if ($editorKey !== null)
                <div data-block-key="{{ $editorKey }}" data-block-type="{{ $block['type'] }}">
                    <x-dynamic-component
                        :component="$component"
                        :attributes="new \Illuminate\View\ComponentAttributeBag($blockClass::mutateData($blockData) + $bindAttributes)"
                    />
                </div>
            @else
                <x-dynamic-component
                    :component="$component"
                    :attributes="new \Illuminate\View\ComponentAttributeBag($blockClass::mutateData($blockData) + $bindAttributes)"
                />
            @endif
        @elseif ($editorKey !== null)
            <div
                data-block-key="{{ $editorKey }}"
                data-block-type="{{ $block['type'] }}"
                style="margin: 0.75rem; padding: 2.5rem 1.5rem; border: 2px dashed #f59e0b; border-radius: 0.5rem; background: #fffbeb; color: #92400e; font-family: ui-sans-serif, system-ui, sans-serif; text-align: center;"
            >
                This "{{ $block['type'] }}" block needs business details that aren't set up yet — it is hidden on the live site.
            </div>
        @endif
    @elseif ($editorKey !== null)
        <div
            data-block-key="{{ $editorKey }}"
            data-block-type="{{ is_array($block) && filled($block['type'] ?? null) ? $block['type'] : 'broken' }}"
            style="margin: 0.75rem; padding: 2.5rem 1.5rem; border: 2px dashed #f59e0b; border-radius: 0.5rem; background: #fffbeb; color: #92400e; font-family: ui-sans-serif, system-ui, sans-serif; text-align: center;"
        >
            This block can't be rendered ({{ is_array($block) ? ($block['type'] ?? 'missing type') : 'malformed entry' }}) — it is hidden on the live site.
        </div>
    @endif
@endforeach

@if ($insertable && is_array($editorKeys))
    <button type="button" data-editor-insert="{{ count($blocks) }}" title="Insert a block here"><span>＋ Add block</span></button>
@endif
