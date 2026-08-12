{{--
    One input of the public enquiry form: a visible label, error styling keyed
    off the form's own bag, and the per-field message underneath. The
    error-class rule lives here once — the form used to spell it four times.

    The label sits ABOVE the field rather than floating into it. A label that
    disappears the moment someone types costs them the one thing they need
    while checking their own phone number back, and on a form this short there
    is room to simply say what each box is for.

    `min`/`max`/`required` are native constraint attributes for the picker
    inputs (date, time, number) the reservation form adds; the server-side
    rules in StoreLeadRequest are the real gate, these just save a round trip.
--}}
@props([
    'bag',
    'formId',
    'field',
    'label',
    'type' => 'text',
    'maxlength' => null,
    'autocomplete' => null,
    'min' => null,
    'max' => null,
    'required' => false,
    'textarea' => false,
    'rows' => 4,
])
@php
    // Scoped by the form's own id, not a random one: a page may carry several
    // enquiry forms (a contact block and the offer popup), the label's `for`
    // has to reach the right box in each, and a value that changes per render
    // would make the markup non-deterministic for no gain.
    $id = 'lead-'.$formId.'-'.$field;
@endphp
<div>
    <label class="site-field-label" for="{{ $id }}">{{ $label }}</label>
    @if ($textarea)
        <textarea
            id="{{ $id }}"
            name="{{ $field }}"
            rows="{{ $rows }}"
            @if ($maxlength) maxlength="{{ $maxlength }}" @endif
            @if ($required) required @endif
            @class(['site-field', 'site-field-invalid' => $bag->has($field)])
        >{{ old($field) }}</textarea>
    @else
        <input
            id="{{ $id }}"
            type="{{ $type }}"
            name="{{ $field }}"
            value="{{ old($field) }}"
            @if ($maxlength) maxlength="{{ $maxlength }}" @endif
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if ($min !== null) min="{{ $min }}" @endif
            @if ($max !== null) max="{{ $max }}" @endif
            @if ($required) required @endif
            @class(['site-field', 'site-field-invalid' => $bag->has($field)])
        />
    @endif
    <x-lead-form-error :bag="$bag" :field="$field" />
</div>
