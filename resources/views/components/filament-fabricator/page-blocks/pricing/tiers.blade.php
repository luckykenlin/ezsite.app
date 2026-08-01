@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'intro' => null,
    'plans' => [],
])
@php
    // Repeater state may be a uuid-keyed map (Filament) or a plain list (AI
    // output) — iterate whatever array arrives, defensively.
    //
    // Unnamed plans are dropped HERE rather than skipped in the loop, for the
    // same reason as the simple variant: a half-finished entry the operator is
    // still typing must not claim a column.
    $items = array_values(array_filter(
        is_array($plans) ? $plans : [],
        static fn (mixed $plan): bool => is_array($plan) && ($plan['name'] ?? null),
    ));

    // At most ONE plan is highlighted, whatever the data says: two "recommended"
    // columns recommend nothing, and an operator toggling a second without
    // clearing the first is the likely mistake. First wins.
    $featured = null;

    foreach ($items as $index => $item) {
        if (($item['is_featured'] ?? false) === true) {
            $featured = $index;

            break;
        }
    }
@endphp
<x-site.section :appearance="$appearance" tone="base" spacing="normal">
    <div class="mx-auto max-w-6xl px-6">
        @if ($heading)
            <h2 class="site-h2 text-center">{{ $heading }}</h2>
        @endif

        @if ($intro)
            <p class="site-intro mx-auto mt-4 max-w-2xl text-center text-base-content/70">{{ $intro }}</p>
        @endif

        {{-- items-center rather than items-start: the featured tier reads as the
             tallest card only when its neighbours float at mid-height. --}}
        <div class="mt-12 grid gap-8 lg:grid-cols-3 lg:items-center">
            @foreach ($items as $index => $item)
                @php
                    $isFeatured = $index === $featured;
                    // One per line (see the block class for why this is a string
                    // and not a nested repeater). Blank lines are dropped so a
                    // stray newline never renders an empty bullet.
                    $lines = array_values(array_filter(
                        array_map('trim', preg_split('/\R/', (string) ($item['features'] ?? '')) ?: []),
                        static fn (string $line): bool => $line !== '',
                    ));
                @endphp
                <div @class([
                    'card bg-base-200',
                    'ring-2 ring-primary' => $isFeatured,
                ])>
                    <div class="card-body">
                        @if ($isFeatured)
                            <span class="site-eyebrow text-primary">{{ __('Most popular') }}</span>
                        @endif

                        <h3 class="card-title">{{ $item['name'] }}</h3>

                        @if ($item['price'] ?? null)
                            <p class="mt-2">
                                <span class="text-4xl font-bold tabular-nums">{{ $item['price'] }}</span>
                                @if ($item['period'] ?? null)
                                    <span class="text-base-content/60">{{ $item['period'] }}</span>
                                @endif
                            </p>
                        @endif

                        @if ($item['description'] ?? null)
                            <p class="text-base-content/70">{{ $item['description'] }}</p>
                        @endif

                        @if ($lines !== [])
                            <ul class="mt-4 space-y-2">
                                @foreach ($lines as $line)
                                    <li class="flex gap-2">
                                        <span class="text-primary" aria-hidden="true">✓</span>
                                        <span>{{ $line }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if (($item['cta_label'] ?? null) && ($item['cta_url'] ?? null))
                            <div class="card-actions mt-6">
                                <a
                                    href="{{ $item['cta_url'] }}"
                                    @class(['btn w-full', 'btn-primary' => $isFeatured, 'btn-ghost' => ! $isFeatured])
                                >{{ $item['cta_label'] }}</a>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-site.section>
