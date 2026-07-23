@aware(['page'])
@props([
    'content' => null,
    'level' => 'h2',
])
@php
    // Whitelist the tag so the stored value can never inject markup, and map each
    // level to a DaisyUI-friendly type scale (semantic colour, no hardcoded hex).
    $scale = [
        'h1' => 'text-4xl md:text-5xl font-bold',
        'h2' => 'text-3xl md:text-4xl font-bold',
        'h3' => 'text-2xl md:text-3xl font-semibold',
        'h4' => 'text-xl md:text-2xl font-semibold',
        'h5' => 'text-lg md:text-xl font-semibold',
        'h6' => 'text-base md:text-lg font-semibold',
    ];
    $tag = array_key_exists($level, $scale) ? $level : 'h2';
@endphp
<section class="px-4 py-8 md:py-12">
    <div class="mx-auto max-w-7xl">
        <{{ $tag }} class="text-balance tracking-tight text-base-content {{ $scale[$tag] }}">{{ $content }}</{{ $tag }}>
    </div>
</section>
