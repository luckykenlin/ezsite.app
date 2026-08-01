@aware(['page'])
@props([
    'appearance' => null,
    'eyebrow' => null,
    'heading' => null,
    'subheading' => null,
    'cta_label' => null,
    'cta_url' => null,
    'image_url' => null,
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('hero', 'centered-minimal')->resolve($appearance);

    // A hero's headline has its own display scale, so the align axis lands on
    // the flex column rather than through the shared section header.
    $centered = str_contains($layout->heading(), 'text-center');
@endphp
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div @class([$layout->container(), 'flex flex-col', 'items-center text-center' => $centered, 'items-start' => ! $centered])>
        @if ($eyebrow)
            <p data-editor-field="eyebrow" class="site-eyebrow mb-4 text-primary">{{ $eyebrow }}</p>
        @endif

        <h1 data-editor-field="heading" class="site-display">{{ $heading }}</h1>

        @if ($subheading)
            <p data-editor-field="subheading" class="site-intro mt-6 max-w-2xl text-base-content/70">{{ $subheading }}</p>
        @endif

        @if ($cta_label && $cta_url)
            <a href="{{ $cta_url }}" class="btn btn-primary mt-10">{{ $cta_label }}</a>
        @endif
    </div>
</x-site.section>
