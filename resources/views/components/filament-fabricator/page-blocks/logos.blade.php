@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'logos' => [],
])
@php
    // Repeater state may be a uuid-keyed map (Filament) or a plain list (AI
    // output) — iterate whatever array arrives, defensively.
    $items = array_values(array_filter(is_array($logos) ? $logos : [], 'is_array'));
@endphp
<x-site.section :appearance="$appearance" tone="muted" spacing="tight">
    <div class="mx-auto max-w-6xl px-6">
        @if ($heading)
            <h2 class="text-center text-sm font-semibold uppercase tracking-widest text-base-content/60">{{ $heading }}</h2>
        @endif

        <div @class(['flex flex-wrap items-center justify-center gap-x-12 gap-y-8', 'mt-8' => (bool) $heading])>
            @foreach ($items as $item)
                {{-- Skipped rather than rendered empty, as in gallery: a trust row
                     with a hole in it does the opposite of its job. `name` carries
                     the meaning here, so a nameless entry is skipped too. --}}
                @continue(! ($item['url'] ?? null) || ! ($item['name'] ?? null))
                @php
                    // media_id resolves into `url`; link_url is scheme-checked.
                    // Both already safe to emit — see BlockRegistry.
                    $link = $item['link_url'] ?? null;
                @endphp
                @if ($link)
                    <a href="{{ $link }}" class="transition hover:opacity-100">
                        {{-- The brand name IS the image, so it is the alt text, not
                             decoration — without it a screen reader gets nothing. --}}
                        <img
                            src="{{ $item['url'] }}"
                            alt="{{ $item['name'] }}"
                            loading="lazy"
                            class="h-10 w-auto opacity-70 grayscale transition hover:opacity-100 hover:grayscale-0"
                        />
                    </a>
                @else
                    <img
                        src="{{ $item['url'] }}"
                        alt="{{ $item['name'] }}"
                        loading="lazy"
                        class="h-10 w-auto opacity-70 grayscale"
                    />
                @endif
            @endforeach
        </div>
    </div>
</x-site.section>
