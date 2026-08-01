@aware(['page'])
@props([
    'appearance' => null,
    'content' => null,
    'level' => 'h2',
])
@php
    // Whitelist the tag so the stored value can never inject markup, and map each
    // level to the shared site typography scale (semantic colour, no hardcoded hex).
    $scale = [
        'h1' => 'site-h1',
        'h2' => 'site-h2',
        'h3' => 'site-h3',
        'h4' => 'site-h4',
        'h5' => 'site-h5',
        'h6' => 'site-h6',
    ];
    $tag = array_key_exists($level, $scale) ? $level : 'h2';
@endphp
<x-site.section :appearance="$appearance" tone="plain" spacing="flush" class="px-4">
    <div class="mx-auto max-w-7xl">
        <{{ $tag }} data-editor-field="content" class="text-base-content {{ $scale[$tag] }}">{{ $content }}</{{ $tag }}>
    </div>
</x-site.section>
