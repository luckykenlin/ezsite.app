@aware(['page'])
@props([
    'appearance' => null,
    'eyebrow' => null,
    'heading' => null,
    'figures' => [],
    'quote' => null,
    'quote_author' => null,
    'quote_role' => null,
    'image_url' => null,
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('credentials')->resolve($appearance);

    // Three at most, whatever was stored: a fourth figure turns a statement
    // into a spreadsheet.
    $stats = array_slice(array_values(array_filter(
        is_array($figures) ? $figures : [],
        static fn (mixed $figure): bool => is_array($figure) && filled($figure['value'] ?? null),
    )), 0, 3);
    $centered = str_contains($layout->heading(), 'text-center');
@endphp
{{-- The studio's proof: the heading and its figures on one side, a tall
     photograph on the other with the founder's words on a card laid over
     its lower edge. No photograph keeps the frame as a blush panel. --}}
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div class="{{ $layout->container() }} site-credentials">
        <div @class(['site-head', 'site-head-center' => $centered])>
            <x-site.ornament />
            @if ($eyebrow)
                <p data-editor-field="eyebrow" class="site-eyebrow site-accent-ink mt-3">{{ $eyebrow }}</p>
            @endif
            @if ($heading)
                <h2 data-editor-field="heading" class="{{ $layout->heading() }} mt-3">
                    <x-site.emphasis :text="$heading" />
                </h2>
            @endif

            @if ($stats !== [])
                <dl class="site-figures">
                    @foreach ($stats as $figure)
                        <div class="site-figure">
                            <dt data-editor-field="figures.{{ $loop->index }}.label" class="site-figure-label site-dim">
                                {{ $figure['label'] ?? '' }}
                            </dt>
                            <dd data-editor-field="figures.{{ $loop->index }}.value" class="site-figure-value">
                                {{ $figure['value'] }}
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </div>

        <div class="site-credentials-media">
            <div class="site-credentials-photo">
                @if ($image_url)
                    <img src="{{ $image_url }}" alt="" loading="lazy" class="site-arch-img" />
                @else
                    <div class="site-arch-empty"><x-site.ornament class="site-ornament-lg" /></div>
                @endif
            </div>

            @if ($quote)
                <figure class="site-credentials-quote">
                    <span class="site-quote-mark" aria-hidden="true">&ldquo;</span>
                    <blockquote data-editor-field="quote" class="site-quote-text">{{ $quote }}</blockquote>
                    @if ($quote_author)
                        <figcaption class="mt-4">
                            <span
                                data-editor-field="quote_author"
                                class="site-accent-ink block"
                            >{{ $quote_author }}</span>
                            @if ($quote_role)
                                <span
                                    data-editor-field="quote_role"
                                    class="site-dim-soft block"
                                >{{ $quote_role }}</span>
                            @endif
                        </figcaption>
                    @endif
                </figure>
            @endif
        </div>
    </div>
</x-site.section>
