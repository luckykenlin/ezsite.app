@aware(['page'])
@props([
    'appearance' => null,
    'content' => null,
    'level' => 'h2',
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('heading')->resolve($appearance);

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
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    {{-- The align axis lands on the heading itself: a heading block IS its
         header, so the section-header component's fixed site-h2 scale does
         not fit the level-mapped tag here. --}}
    <div class="{{ $layout->container() }}">
        <{{ $tag }}
            data-editor-field="content"
            @class(['text-base-content', $scale[$tag], 'text-center' => str_contains($layout->heading(), 'text-center')])
            >{{ $content }}</{{ $tag }}
        >
    </div>
</x-site.section>
