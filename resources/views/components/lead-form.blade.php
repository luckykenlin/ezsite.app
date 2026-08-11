{{--
    The public enquiry form, shared by every capture surface: the contact
    block, the inline signup block, and the site-wide popup. Posts to the
    tenant's own `leads.store` route (see StoreLeadController).

    Without JavaScript, success and validation come back through the session
    exactly as they always did — so the form still works with JS off, and in
    the page editor's canvas (where clicks are swallowed in the capture phase,
    so it can't be submitted by accident). With `resources/js/site.ts` loaded
    the submit is intercepted and answered as JSON, which is what lets the
    popup succeed without a page reload.

    `formId` is what makes several forms on one page possible: it keys the
    anchor, the success flash and the validation error bag, so a failed popup
    never paints its errors onto the contact form further down the page. It is
    required — a caller that omitted it would silently share state with every
    other form.

    `_hp` is the honeypot: hidden from humans, irresistible to bots.
--}}
@use('App\Enums\LeadFieldSet')
@use('App\Enums\LeadSource')
@use('App\Site\LeadFormIds')
@props([
    'formId',
    'location' => null,
    'page' => null,
    'successMessage' => null,
    'source' => LeadSource::ContactForm,
    'fields' => LeadFieldSet::Full,
    'buttonLabel' => null,
    'buttonClass' => 'site-btn site-btn-primary',
    'finePrint' => null,
])
@php
    $bag = $errors->getBag(LeadFormIds::errorBag($formId));
    $submitted = session(LeadFormIds::SUBMITTED_SESSION_KEY) === $formId;
    $thanks = $successMessage ?: __('Thanks — we got your message and will be in touch.');
    // A single-line set (no message box) puts its inputs side by side, which
    // is what makes the inline signup read as one row rather than a stack.
    $side = $fields->isSingleLine() && $fields->showsEmail() && $fields->showsPhone();
@endphp

{{--
    Both states are always in the DOM, toggled by the `hidden` attribute
    rather than one replacing the other: the JS path needs the success panel
    present before the request, and the no-JS path needs the form present
    after it (so a visitor who submits twice in a session still sees a form).
    `hidden` and not a class, so the toggle survives a missing stylesheet.
--}}
<div id="{{ LeadFormIds::anchor($formId) }}" {{ $attributes->class(['w-full']) }}>
    <div data-lead-success role="status" @if (! $submitted) hidden @endif>
        <div class="site-alert">
            <span data-lead-success-message>{{ $thanks }}</span>
        </div>
    </div>

    <form
        method="POST"
        action="{{ route('leads.store') }}"
        class="space-y-4"
        data-lead-form
        @if ($submitted) hidden @endif
    >
        @csrf

        <input type="hidden" name="form_id" value="{{ $formId }}" />
        <input type="hidden" name="source" value="{{ $source->value }}" />

        @if ($location)
            <input type="hidden" name="location_id" value="{{ $location->id }}" />
        @endif

        @if ($page && $page->id)
            <input type="hidden" name="page_id" value="{{ $page->id }}" />
        @endif

        <div class="hidden" aria-hidden="true">
            <label for="lead-hp-{{ $formId }}">{{ __('Leave this field empty') }}</label>
            <input id="lead-hp-{{ $formId }}" type="text" name="_hp" value="" tabindex="-1" autocomplete="off" />
        </div>

        @if ($fields->showsName())
            <x-lead-form-field
                :bag="$bag"
                :form-id="$formId"
                field="name"
                :label="__('Your name')"
                maxlength="120"
                autocomplete="name"
            />
        @endif

        <div @class(['grid gap-4', 'sm:grid-cols-2' => $side])>
            @if ($fields->showsPhone())
                <x-lead-form-field
                    :bag="$bag"
                    :form-id="$formId"
                    field="phone"
                    :label="__('Phone')"
                    type="tel"
                    maxlength="40"
                    autocomplete="tel"
                />
            @endif

            @if ($fields->showsEmail())
                <x-lead-form-field
                    :bag="$bag"
                    :form-id="$formId"
                    field="email"
                    :label="__('Email')"
                    type="email"
                    maxlength="255"
                    autocomplete="email"
                />
            @endif
        </div>

        @if ($fields->showsMessage())
            <x-lead-form-field
                :bag="$bag"
                :form-id="$formId"
                field="message"
                :label="__('How can we help?')"
                :textarea="true"
                maxlength="2000"
            />
        @endif

        {{-- The submit button's colours come from whoever placed the form:
             `btn-primary` is invisible on a section whose background IS the
             primary colour, which is every signup block's own tone default
             (see App\Site\Blocks\SectionTone::buttonClasses()). The default here
             is for a caller with no section tone to ask — the offer popup, which
             paints its own dialog surface. --}}
        <button type="submit" class="{{ $buttonClass }}">{{ $buttonLabel ?: __('Send message') }}</button>

        @if ($finePrint)
            <p class="text-xs opacity-70">{{ $finePrint }}</p>
        @endif
    </form>
</div>
