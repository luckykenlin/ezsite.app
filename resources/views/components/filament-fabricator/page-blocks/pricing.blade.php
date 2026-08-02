@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'intro' => null,
    'plans' => [],
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('pricing')->resolve($appearance);

    // Repeater state may be a uuid-keyed map (Filament) or a plain list (AI
    // output) — iterate whatever array arrives, defensively.
    //
    // Unnamed plans are dropped HERE rather than skipped in the loop, because a
    // half-finished entry the operator is still typing would otherwise claim a
    // column and leave the real plan rendering at half width.
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
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div class="{{ $layout->container() }}">
        <x-site.section-header :layout="$layout" :heading="$heading" :intro="$intro" />

        {{-- Capped at the plan count: one plan across the full width, never a third of a grid. --}}
        <div class="mt-12 grid items-start gap-8 {{ $layout->gridFor(count($items)) }}">
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
                    $layout->item(),
                    'h-full' => $layout->isCard(),
                    'relative ring-1 ring-primary' => $isFeatured && $layout->isCard(),
                ])>
                    {{-- The badge sits astride the card's top edge — a quieter
                         highlight than doubling the ring weight. --}}
                    @if ($isFeatured && $layout->isCard())
                        <span class="badge badge-primary absolute -top-3 left-1/2 -translate-x-1/2">Recommended</span>
                    @endif
                    <div @class(['card-body' => $layout->isCard()])>
                        @if ($isFeatured && ! $layout->isCard())
                            <span class="badge badge-primary self-start">Recommended</span>
                        @endif

                        <h3 @class(['card-title' => $layout->isCard(), 'text-lg font-semibold' => ! $layout->isCard()])>{{ $item['name'] }}</h3>

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
                            <div @class(['card-actions mt-6' => $layout->isCard(), 'mt-6' => ! $layout->isCard()])>
                                <a
                                    href="{{ $item['cta_url'] }}"
                                    @class(['btn w-full', 'btn-primary' => $isFeatured, 'btn-outline' => ! $isFeatured])
                                >{{ $item['cta_label'] }}</a>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-site.section>
