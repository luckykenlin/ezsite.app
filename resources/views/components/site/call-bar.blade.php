{{--
    The sticky mobile call bar: a single tap-to-call button fixed to the bottom
    of the viewport on phones.

    The cheapest rung of the capture ladder and, for a local business, often
    the most productive one — most traffic arrives on a phone from a maps or
    search result, and someone ready to book would rather tap once than fill in
    even a two-field form. It deliberately does NOT create a Lead: a call is a
    call, and writing a row per tap would fill the inbox with unqualified
    clicks the operator cannot reply to.

    Phones only (`sm:hidden`) — on a desktop there is nothing to tap and the
    number is already in the header and footer.

    Renders nothing when the business has no phone number at all, which is the
    only thing on it.
--}}
@props(['capture'])
@php
    $resolver = resolve(\App\Site\BindResolver::class);
    $business = $resolver->business();
    // Null asks for the primary location — the bar is site-wide, so it has no
    // block binding of its own to honour.
    $location = $resolver->location(null);

    // Same fallback the contact block uses: the location's own line first,
    // then the business-wide one.
    $phone = $location?->phone ?? $business?->contact_phone;
@endphp

@if (filled($phone))
    {{-- A spacer of the bar's own height, so the last of the footer is never
         hidden behind it. Part of the flow rather than body padding, which
         would need a global stylesheet rule. --}}
    <div class="h-16 sm:hidden" aria-hidden="true"></div>

    <div data-call-bar class="border-base-300 bg-base-100 fixed inset-x-0 bottom-0 z-40 border-t p-3 sm:hidden">
        <div class="flex gap-2">
            <a href="tel:{{ $phone }}" class="btn btn-primary flex-1"> {{ $capture->callBarLabel() }} </a>

            @if ($capture->callBarOffersPopup())
                {{-- Opening the popup needs JavaScript, so this button only
                     appears once the enhancement has run — a dead button is
                     worse than no button. --}}
                <button type="button" class="btn btn-outline flex-1" data-call-bar-popup hidden>
                    {{ __('Message us') }}
                </button>
            @endif
        </div>
    </div>
@endif
