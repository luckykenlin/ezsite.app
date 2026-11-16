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
    $layout = \App\Site\Blocks\SectionLayout::for('hero', 'arch')->resolve($appearance);

    $centered = str_contains($layout->heading(), 'text-center');
@endphp
{{-- The studio's opening: a serif headline with its italic phrase on the
     left, one tall arched photograph on the right. The arch IS the variant —
     with no photograph it keeps its shape as a blush-tinted frame carrying
     the ornament, so a fresh site still opens on a designed composition. --}}
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div class="{{ $layout->container() }} site-hero-arch">
        <div @class(['flex flex-col', 'items-center text-center' => $centered, 'items-start' => ! $centered])>
            @if ($eyebrow)
                <p data-editor-field="eyebrow" class="site-eyebrow site-pill-label">{{ $eyebrow }}</p>
            @endif

            <h1 data-editor-field="heading" class="site-display-lg mt-6"><x-site.emphasis :text="$heading" /></h1>

            @if ($subheading)
                <p data-editor-field="subheading" class="site-intro site-dim mt-6 max-w-md">{{ $subheading }}</p>
            @endif

            @if ($cta_label && $cta_url)
                <a href="{{ $cta_url }}" class="{{ $layout->button() }} mt-10"
                    >{{ $cta_label }}<span class="site-btn-dot" aria-hidden="true"></span
                ></a>
            @endif
        </div>

        <div class="site-arch site-arch-hero">
            @if ($image_url)
                <img src="{{ $image_url }}" alt="{{ str_replace('*', '', (string) $heading) }}" class="site-arch-img" />
            @else
                <div class="site-arch-empty"><x-site.ornament class="site-ornament-lg" /></div>
            @endif
        </div>
    </div>
</x-site.section>
