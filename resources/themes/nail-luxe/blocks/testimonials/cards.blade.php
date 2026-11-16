@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'testimonials' => [],
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('testimonials', 'cards')->resolve($appearance);

    $items = array_values(array_filter(is_array($testimonials) ? $testimonials : [], 'is_array'));
    $centered = str_contains($layout->heading(), 'text-center');
@endphp
{{-- Rounded quote cards: a small quote mark, the words in the serif, the
     client's name underneath in the accent ink. --}}
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div class="{{ $layout->container() }}">
        @if ($heading)
            <div @class(['site-head', 'site-head-center' => $centered])>
                <x-site.ornament />
                <h2 data-editor-field="heading" class="{{ $layout->heading() }}">
                    <x-site.emphasis :text="$heading" />
                </h2>
            </div>
        @endif

        <div class="{{ $layout->headerGap() }} grid gap-6 {{ $layout->gridFor(count($items)) }}">
            @foreach ($items as $item)
                <figure class="{{ $layout->item() }} site-quote-card">
                    <div @class(['site-card-body' => $layout->isCard(), 'site-quote-body'])>
                        <span class="site-quote-mark" aria-hidden="true">&ldquo;</span>
                        <blockquote data-editor-field="testimonials.{{ $loop->index }}.quote" class="site-quote-text">
                            {{ $item['quote'] ?? '' }}
                        </blockquote>
                        <figcaption class="site-quote-by">
                            @if ($item['avatar_url'] ?? null)
                                <img src="{{ $item['avatar_url'] }}" alt="" loading="lazy" class="site-quote-avatar" />
                            @endif
                            <span>
                                <span
                                    data-editor-field="testimonials.{{ $loop->index }}.author"
                                    class="site-accent-ink block"
                                >{{ $item['author'] ?? '' }}</span>
                                @if ($item['role'] ?? null)
                                    <span
                                        data-editor-field="testimonials.{{ $loop->index }}.role"
                                        class="site-dim-soft block"
                                    >{{ $item['role'] }}</span>
                                @endif
                            </span>
                        </figcaption>
                    </div>
                </figure>
            @endforeach
        </div>
    </div>
</x-site.section>
