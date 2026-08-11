@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'intro' => null,
    'members' => [],
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('team')->resolve($appearance);

    // Repeater state may be a uuid-keyed map (Filament) or a plain list (AI
    // output) — iterate whatever array arrives, defensively.
    $items = array_values(array_filter(is_array($members) ? $members : [], 'is_array'));

    // The two old variants live on as axis combinations: portrait circles are
    // image_shape=circle on plain items (centred), photo tiles are a squarer
    // crop on cards (left-aligned, photo bleeding to the card edge).
    $circle = str_contains($layout->image(), 'rounded-full');
@endphp
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div class="{{ $layout->container() }}">
        <x-site.section-header :layout="$layout" :heading="$heading" :intro="$intro" />

        <div class="{{ $layout->headerGap() }} grid gap-8 {{ $layout->grid() }}">
            @foreach ($items as $item)
                @continue(! ($item['name'] ?? null))
                @php
                    // resolveMediaUrls() injects avatar_url per repeater item from
                    // avatar_media_id, and drops any URL whose scheme executes —
                    // see BlockRegistry::denyExecutableUrls().
                    $avatar = $item['avatar_url'] ?? null;
                    // Initials rather than a broken frame when there is no photo:
                    // mb_* because a tenant's names are not necessarily ASCII.
                    $initials = mb_strtoupper(mb_substr(mb_trim($item['name']), 0, 1));
                @endphp
                <div @class([$layout->item(), 'overflow-hidden' => $layout->isCard(), 'text-center' => ! $layout->isCard()])>
                    @if ($avatar)
                        {{-- Decorative: the name below is the accessible label, so
                             an alt here would be announced twice. --}}
                        <img
                            src="{{ $avatar }}"
                            alt=""
                            loading="lazy"
                            @class([$layout->image(), 'object-cover', 'mx-auto size-28 shadow-md ring-2 ring-base-100' => $circle, 'w-full' => ! $circle])
                        />
                    @else
                        <span
                            @class(['flex items-center justify-center bg-base-300 font-bold text-base-content/50', $layout->image(), 'mx-auto size-28 text-3xl shadow-md ring-2 ring-base-100' => $circle, 'w-full text-5xl' => ! $circle])
                            aria-hidden="true"
                        >{{ $initials }}</span>
                    @endif
                    <div @class(['site-card-body' => $layout->isCard()])>
                        <h3
                            data-editor-field="members.{{ $loop->index }}.name"
                            @class(['site-h5', 'mt-4' => ! $layout->isCard()])
                        >
                            {{ $item['name'] }}
                        </h3>
                        @if ($item['role'] ?? null)
                            <p
                                data-editor-field="members.{{ $loop->index }}.role"
                                class="text-primary text-sm font-medium"
                            >
                                {{ $item['role'] }}
                            </p>
                        @endif
                        @if ($item['bio'] ?? null)
                            <p
                                data-editor-field="members.{{ $loop->index }}.bio"
                                @class(['site-dim', 'mt-2' => ! $layout->isCard()])
                            >
                                {{ $item['bio'] }}
                            </p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-site.section>
