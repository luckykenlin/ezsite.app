{{--
    The site-wide offer popup: one per site, rendered after the footer on every
    public page, opened by resources/js/site/popup.ts.

    A native <dialog> rather than a div-and-overlay, because the platform
    already solves the hard parts: `showModal()` traps focus, marks the rest of
    the document inert, and closes on Escape. Without JavaScript the element
    simply never opens — which is the correct degradation for a popup, and why
    nothing important may ever live only in here.

    It is NEVER rendered on the page editor's canvas (see the layout): a modal
    the operator could not dismiss would sit on top of the page they are
    editing.

    The trigger config rides on data attributes so the script needs no inline
    JSON, and App\Site\SiteCapture has already clamped every value.
--}}
@props(['capture', 'page' => null])

<dialog
    class="modal"
    data-site-popup
    data-popup-trigger="{{ $capture->popupTrigger()->value }}"
    data-popup-value="{{ $capture->popupTriggerValue() }}"
    data-popup-frequency="{{ $capture->popupFrequencyDays() }}"
    aria-labelledby="site-popup-heading"
>
    <div class="modal-box">
        {{-- Inside a <form method="dialog"> so the close button works with no
             JavaScript at all once the dialog is open — a visitor must always
             be able to get out. --}}
        <form method="dialog">
            <button
                class="btn btn-circle btn-ghost btn-sm absolute end-3 top-3"
                aria-label="{{ __('Close') }}"
            >✕</button>
        </form>

        <h2 id="site-popup-heading" class="site-h3">{{ $capture->popupHeading() }}</h2>

        @if ($capture->popupOffer() !== '')
            <p class="mt-2 opacity-80">{{ $capture->popupOffer() }}</p>
        @endif

        <div class="mt-6">
            <x-lead-form
                form-id="popup"
                :page="$page"
                :source="\App\Enums\LeadSource::Popup"
                :fields="$capture->popupFields()"
                :button-label="$capture->popupButtonLabel()"
                :success-message="$capture->popupSuccessMessage()"
                :fine-print="$capture->popupFinePrint()"
            />
        </div>
    </div>

    {{-- daisyUI's click-outside-to-close backdrop, also a plain dialog form. --}}
    <form method="dialog" class="modal-backdrop">
        <button>{{ __('Close') }}</button>
    </form>
</dialog>
