@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'testimonials' => [],
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('testimonials', 'grid')->resolve($appearance);

    $items = is_array($testimonials) ? $testimonials : [];
@endphp
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div class="{{ $layout->container() }}">
        <x-site.section-header :layout="$layout" :heading="$heading" />

        <div class="mt-12 grid gap-8 {{ $layout->grid() }}">
            @foreach ($items as $item)
                @continue(! is_array($item))
                <figure class="{{ $layout->item() }}">
                    <div @class(['card-body' => $layout->isCard()])>
                        <blockquote
                            data-editor-field="testimonials.{{ $loop->index }}.quote"
                            class="site-quote text-lg leading-relaxed"
                        >
                            {{ $item['quote'] ?? '' }}
                        </blockquote>
                        <figcaption class="mt-4 flex items-center gap-3">
                            @if ($item['avatar_url'] ?? null)
                                <img
                                    src="{{ $item['avatar_url'] }}"
                                    alt="{{ $item['author'] ?? '' }}"
                                    class="ring-base-content/10 size-10 rounded-full object-cover ring-1"
                                />
                            @endif
                            <div>
                                <span
                                    data-editor-field="testimonials.{{ $loop->index }}.author"
                                    class="font-semibold"
                                >{{ $item['author'] ?? '' }}</span>
                                @if ($item['role'] ?? null)
                                    <span
                                        data-editor-field="testimonials.{{ $loop->index }}.role"
                                        class="text-base-content/60 block text-sm"
                                    >{{ $item['role'] }}</span>
                                @endif
                            </div>
                        </figcaption>
                    </div>
                </figure>
            @endforeach
        </div>
    </div>
</x-site.section>
