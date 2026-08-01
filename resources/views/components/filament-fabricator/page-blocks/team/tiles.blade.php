@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'intro' => null,
    'members' => [],
])
@php
    // Repeater state may be a uuid-keyed map (Filament) or a plain list (AI
    // output) — iterate whatever array arrives, defensively.
    $items = array_values(array_filter(is_array($members) ? $members : [], 'is_array'));
@endphp
<x-site.section :appearance="$appearance" tone="base" spacing="normal">
    <div class="mx-auto max-w-6xl px-6">
        @if ($heading)
            <h2 class="site-h2 text-center">{{ $heading }}</h2>
        @endif

        @if ($intro)
            <p class="site-intro mx-auto mt-4 max-w-2xl text-center text-base-content/70">{{ $intro }}</p>
        @endif

        <div class="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($items as $item)
                @continue(! ($item['name'] ?? null))
                @php
                    // resolveMediaUrls() injects avatar_url per repeater item from
                    // avatar_media_id, and drops any URL whose scheme executes —
                    // see BlockRegistry::denyExecutableUrls().
                    $avatar = $item['avatar_url'] ?? null;
                    // Initials rather than a broken frame when there is no photo:
                    // mb_* because a tenant's names are not necessarily ASCII.
                    $initials = mb_strtoupper(mb_substr(trim($item['name']), 0, 1));
                @endphp
                <div class="card overflow-hidden bg-base-200">
                    @if ($avatar)
                        {{-- Decorative: the name below is the accessible label, so
                             an alt here would be announced twice. --}}
                        <img src="{{ $avatar }}" alt="" loading="lazy" class="aspect-[4/5] w-full object-cover" />
                    @else
                        <span
                            class="flex aspect-[4/5] items-center justify-center bg-base-300 text-5xl font-bold text-base-content/40"
                            aria-hidden="true"
                        >{{ $initials }}</span>
                    @endif
                    <div class="card-body">
                        <h3 class="card-title">{{ $item['name'] }}</h3>
                        @if ($item['role'] ?? null)
                            <p class="text-sm font-medium text-primary">{{ $item['role'] }}</p>
                        @endif
                        @if ($item['bio'] ?? null)
                            <p class="text-base-content/70">{{ $item['bio'] }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-site.section>
