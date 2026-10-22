@aware(['page'])
@props([
    'appearance' => null,
    'content' => null,
    'level' => 'h2',
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('heading')->resolve($appearance);

    // Whitelisted tag, mapped onto the shared type scale — the stored value
    // can never inject markup.
    $scale = [
        'h1' => 'site-h1',
        'h2' => 'site-h2',
        'h3' => 'site-h3',
        'h4' => 'site-h4',
        'h5' => 'site-h5',
        'h6' => 'site-h6',
    ];
    $tag = array_key_exists($level, $scale) ? $level : 'h2';
    $centered = str_contains($layout->heading(), 'text-center');
@endphp
{{-- A section title with the theme's ornament over it, so a bare heading
     still reads as part of the studio's set rather than a stray line. --}}
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div @class([$layout->container(), 'site-head', 'site-head-center' => $centered])>
        <x-site.ornament />
        <{{ $tag }} data-editor-field="content" @class([$scale[$tag], 'text-center' => $centered])
            ><x-site.emphasis :text="$content"
        /></{{ $tag }}>
    </div>
</x-site.section>
