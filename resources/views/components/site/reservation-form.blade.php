{{--
    The reservation block's lead form: the shared x-lead-form (honeypot,
    success toggle, per-form error bag) asking for name + phone, with the
    three booking facts slotted in between the identity fields and the button.

    The date input's `min` is "today" on the LOCATION's clock, not the
    server's — at 1am UTC a New York diner is still living yesterday evening,
    and greying out "tonight" would cost a booking. StoreLeadRequest re-checks
    the same rule server-side against the same clock.

    `maxPartySize` comes from block data, so it is untrusted by the time it
    posts back; it only shapes the input here, and the server holds its own
    1–50 ceiling. Deriving the form id in a php block is load-bearing, not
    style: Blade evaluates an anonymous component's attribute expressions
    twice, so the counter must run in the body (see signup-form).
--}}
@props([
    'page',
    'location' => null,
    'maxPartySize' => null,
    'buttonLabel' => null,
    'buttonClass' => 'site-btn site-btn-primary',
    'successMessage' => null,
    'finePrint' => null,
])
@php
    $formId = resolve(\App\Site\LeadFormIds::class)->next('reservation');
    $bag = $errors->getBag(\App\Site\LeadFormIds::errorBag($formId));
    $maxGuests = is_numeric($maxPartySize) ? max(1, min((int) $maxPartySize, 50)) : 12;
    $timezone = $location->timezone ?? config()->string('app.timezone');
    $minDate = \Carbon\CarbonImmutable::now($timezone)->format('Y-m-d');
@endphp
<x-lead-form
    :form-id="$formId"
    {{ $attributes }}
    :location="$location"
    :page="$page"
    :source="\App\Enums\LeadSource::Reservation"
    :fields="\App\Enums\LeadFieldSet::NamePhone"
    :button-label="$buttonLabel ?: __('Request a table')"
    :button-class="$buttonClass"
    :success-message="$successMessage ?: __('Request received — we will confirm shortly.')"
    :fine-print="$finePrint"
>
    <div class="grid gap-4 sm:grid-cols-3">
        <x-lead-form-field
            :bag="$bag"
            :form-id="$formId"
            field="reserved_date"
            :label="__('Date')"
            type="date"
            :min="$minDate"
            :required="true"
        />
        <x-lead-form-field
            :bag="$bag"
            :form-id="$formId"
            field="reserved_time"
            :label="__('Time')"
            type="time"
            :required="true"
        />
        <x-lead-form-field
            :bag="$bag"
            :form-id="$formId"
            field="party_size"
            :label="__('Guests')"
            type="number"
            min="1"
            :max="$maxGuests"
            :required="true"
        />
    </div>

    <x-lead-form-field
        :bag="$bag"
        :form-id="$formId"
        field="message"
        :label="__('Anything we should know?')"
        :textarea="true"
        :rows="3"
        maxlength="2000"
    />
</x-lead-form>
