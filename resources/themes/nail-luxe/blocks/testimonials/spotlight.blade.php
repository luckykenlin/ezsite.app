@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'testimonials' => [],
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('testimonials', 'spotlight')->resolve($appearance);

    // One voice at full volume: only the first quote is drawn.
    $items = array_values(array_filter(is_array($testimonials) ? $testimonials : [], 'is_array'));
    $item = $items[0] ?? null;
@endphp
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div class="{{ $layout->container() }} site-head site-head-center text-center">
        <x-site.ornament class="site-ornament-lg" />
        @if ($heading)
            <h2 data-editor-field="heading" class="site-h4 mt-4"><x-site.emphasis :text="$heading" /></h2>
        @endif

        @if ($item)
            <figure class="mt-8">
                <blockquote data-editor-field="testimonials.0.quote" class="site-spotlight-quote">
                    {{ $item['quote'] ?? '' }}
                </blockquote>
                <figcaption class="mt-8">
                    <cite
                        data-editor-field="testimonials.0.author"
                        class="site-role not-italic"
                    >{{ $item['author'] ?? '' }}</cite>
                    @if ($item['role'] ?? null)
                        <span
                            data-editor-field="testimonials.0.role"
                            class="site-dim-soft mt-1 block"
                        >{{ $item['role'] }}</span>
                    @endif
                </figcaption>
            </figure>
        @endif
    </div>
</x-site.section>
