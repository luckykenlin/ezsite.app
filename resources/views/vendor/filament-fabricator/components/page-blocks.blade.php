{{--
    Overrides Z3d0X\FilamentFabricator's block render loop.

    The stock loop skips unknown block types *silently* and errors outright on a
    structurally malformed entry (missing `type`, non-array `data`). On a live,
    single-DB multi-tenant site an AI- or import-produced bad block would then
    500 the whole page. This version delegates resolution to BlockRegistry and
    degrades gracefully: any block it cannot render is skipped and logged, never
    fatal. Rendering stays escaped (`{{ }}`) — block views forbid `{!! !!}`.
--}}
@aware(['page'])
@props(['blocks' => []])

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
    @php
        $component = is_array($block)
            ? \App\Filament\Fabricator\BlockRegistry::resolveComponent($block)
            : null;

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
    @endphp

    @if ($component !== null)
        @php
            $blockClass = \Z3d0X\FilamentFabricator\Facades\FilamentFabricator::getPageBlockFromName($block['type']);
            $blockData = \App\Filament\Fabricator\BlockRegistry::normalizeData($block);
        @endphp

        <x-dynamic-component
            :component="$component"
            :attributes="new \Illuminate\View\ComponentAttributeBag($blockClass::mutateData($blockData))"
        />
    @endif
@endforeach
