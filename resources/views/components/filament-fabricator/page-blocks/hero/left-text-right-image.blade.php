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
    $layout = \App\Site\Blocks\SectionLayout::for('hero', 'left-text-right-image')->resolve($appearance);

    $centered = str_contains($layout->heading(), 'text-center');
@endphp
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    {{-- The two-column split is this variant's structure; the columns axis
         deliberately does not reach it. --}}
    <div class="{{ $layout->container() }} grid items-center gap-12 md:grid-cols-2">
        <div @class(['text-center' => $centered])>
            @if ($eyebrow)
                <p data-editor-field="eyebrow" class="site-eyebrow mb-4 text-primary">{{ $eyebrow }}</p>
            @endif

            <h1 data-editor-field="heading" class="site-h1">{{ $heading }}</h1>

            @if ($subheading)
                <p data-editor-field="subheading" class="site-intro mt-6 text-base-content/70">{{ $subheading }}</p>
            @endif

            @if ($cta_label && $cta_url)
                <a href="{{ $cta_url }}" class="{{ $layout->button() }} mt-8">{{ $cta_label }}</a>
            @endif
        </div>

        @if ($image_url)
            <div class="site-frame isolate">
                <div class="overflow-hidden rounded-box">
                    <img src="{{ $image_url }}" alt="{{ $heading }}" class="{{ $layout->image() }} w-full object-cover" />
                </div>
            </div>
        @endif
    </div>
</x-site.section>
