@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'intro' => null,
    'business' => null,
    'location' => null,
    'show_hours' => true,
    'show_form' => true,
    'success_message' => null,
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('contact')->resolve($appearance);

    // Two columns puts the form beside the details (the old "split" look);
    // one column stacks everything down a centre-or-left spine (the old
    // "stacked" look, which centred).
    $split = $layout->grid() !== 'grid-cols-1';
    $centered = str_contains($layout->heading(), 'text-center');
@endphp
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div @class([$layout->container(), 'grid gap-12' => $split, $layout->grid() => $split, 'flex flex-col' => ! $split, 'items-center text-center' => ! $split && $centered])>
        <div @class(['flex flex-col items-center' => ! $split && $centered, 'w-full' => ! $split])>
            <x-site.section-header :layout="$layout" :heading="$heading" :intro="$intro" />

            @if ($show_form)
                {{-- `#contact` is the anchor nav items and "get in touch"
                     buttons have always pointed at, so it stays on the
                     wrapper; the form carries its own `#lead-contact` for the
                     per-form redirect fragment. --}}
                <div
                    id="contact"
                    @class(['mt-8' => $split, 'order-last mt-10 flex w-full text-start' => ! $split, 'justify-center' => ! $split && $centered])
                >
                    <x-lead-form
                        form-id="contact"
                        class="max-w-xl"
                        :location="$location"
                        :page="$page"
                        :button-class="$layout->button()"
                        :success-message="$success_message"
                    />
                </div>
            @endif
        </div>

        <div @class(['space-y-8' => $split, 'mt-8 w-full space-y-8' => ! $split, 'flex flex-col items-center' => ! $split && $centered])>
            {{-- The same facts the `visit` block leads with, rendered by the
                 same component so the fallback chain (location's phone over
                 the business's) cannot drift between the two. --}}
            <x-site.location-facts :business="$business" :location="$location" :show-hours="$show_hours" />
        </div>
    </div>
</x-site.section>
