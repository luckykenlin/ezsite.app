@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'testimonials' => [],
])
@php
    // Repeater state may be a uuid-keyed map (Filament) or a plain list (AI
    // output) — normalize defensively, then spotlight only the FIRST quote:
    // one voice at full volume is the whole idea of this layout.
    $items = array_values(array_filter(is_array($testimonials) ? $testimonials : [], 'is_array'));
    $item = $items[0] ?? null;
@endphp
<x-site.section :appearance="$appearance" tone="muted" spacing="normal">
    <div class="mx-auto max-w-3xl px-6 text-center">
        @if ($heading)
            <h2 class="site-h2 text-center">{{ $heading }}</h2>
        @endif

        @if ($item)
            <figure @class(['mt-12' => (bool) $heading])>
                <span class="site-display text-primary" aria-hidden="true">&ldquo;</span>
                <blockquote class="site-h3">{{ $item['quote'] ?? '' }}</blockquote>
                <figcaption class="mt-8 flex flex-col items-center gap-3">
                    @if ($item['avatar_url'] ?? null)
                        {{-- Decorative: the cite below is the accessible name, so
                             an alt here would be announced twice. --}}
                        <img src="{{ $item['avatar_url'] }}" alt="" loading="lazy" class="size-14 rounded-full object-cover" />
                    @endif
                    <div>
                        <cite class="not-italic font-semibold">{{ $item['author'] ?? '' }}</cite>
                        @if ($item['role'] ?? null)
                            <span class="block text-sm text-base-content/60">{{ $item['role'] }}</span>
                        @endif
                    </div>
                </figcaption>
            </figure>
        @endif
    </div>
</x-site.section>
