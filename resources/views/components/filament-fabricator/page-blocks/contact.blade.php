@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'intro' => null,
    'business' => null,
    'location' => null,
    'show_form' => true,
    'success_message' => null,
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('contact')->resolve($appearance);

    $addressLines = array_filter([
        $location->address_line1,
        $location->address_line2,
        trim(implode(', ', array_filter([$location->city, $location->state]))." {$location->postal_code}"),
    ]);
    $phone = $location->phone ?? $business->contact_phone;
    $email = $location->email ?? $business->contact_email;
    $hours = $location->opening_hours?->forWeek() ?? [];
    $hasCoordinates = $location->latitude !== null && $location->longitude !== null;

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
                <div id="contact" @class(['mt-8' => $split, 'order-last mt-10 flex w-full text-start' => ! $split, 'justify-center' => ! $split && $centered])>
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
            @if ($addressLines !== [])
                <address class="not-italic leading-relaxed">
                    @foreach ($addressLines as $line)
                        <span class="block">{{ $line }}</span>
                    @endforeach
                </address>
            @endif

            <div class="space-y-1">
                @if ($phone)
                    <p><a href="tel:{{ $phone }}" class="link-hover link">{{ $phone }}</a></p>
                @endif
                @if ($email)
                    <p><a href="mailto:{{ $email }}" class="link-hover link">{{ $email }}</a></p>
                @endif
            </div>

            @if ($hours !== [])
                <table class="table table-sm max-w-sm">
                    <tbody>
                        @foreach ($hours as $day => $ranges)
                            <tr>
                                <th class="font-medium capitalize">{{ $day }}</th>
                                <td>{{ $ranges->isEmpty() ? __('Closed') : implode(', ', $ranges->map(fn ($range): string => (string) $range)) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if ($hasCoordinates)
                <a
                    href="{{ sprintf('https://www.google.com/maps/search/?api=1&query=%s,%s', $location->latitude, $location->longitude) }}"
                    class="btn btn-outline btn-sm"
                    target="_blank"
                    rel="noopener"
                >{{ __('Get directions') }}</a>
            @endif
        </div>
    </div>
</x-site.section>
