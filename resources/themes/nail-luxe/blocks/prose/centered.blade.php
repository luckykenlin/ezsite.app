@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'paragraphs' => [],
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('prose', 'centered')->resolve($appearance);

    $items = is_array($paragraphs) ? $paragraphs : [];
    $centered = str_contains($layout->heading(), 'text-center');
@endphp
{{-- The studio's voice: ornament, a serif heading with its italic phrase,
     and the text set as a narrow, centred reading column. --}}
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div class="{{ $layout->container() }}">
        <div @class(['site-head', 'site-head-center' => $centered])>
            <x-site.ornament />
            @if ($heading)
                <h2 data-editor-field="heading" class="{{ $layout->heading() }}">
                    <x-site.emphasis :text="$heading" />
                </h2>
            @endif
        </div>

        <div @class(['site-prose site-dim-strong', $layout->headerGap() => (bool) $heading, 'text-center' => $centered])>
            @foreach ($items as $item)
                @continue(! is_array($item))
                @php($text = $item['text'] ?? null)
                @continue(! is_string($text) || mb_trim($text) === '')
                <p data-editor-field="paragraphs.{{ $loop->index }}.text">{{ $text }}</p>
            @endforeach
        </div>
    </div>
</x-site.section>
