{{--
    One input of the public enquiry form: floating label, error styling keyed
    off the form's own bag, and the per-field message underneath. The
    error-class rule lives here once — the form used to spell it four times.

    The label doubles as the placeholder: the floating-label pattern shows one
    or the other, never both.
--}}
@props([
    'bag',
    'field',
    'label',
    'type' => 'text',
    'maxlength' => null,
    'autocomplete' => null,
    'textarea' => false,
    'rows' => 4,
])
<div>
    <label class="floating-label">
        <span>{{ $label }}</span>
        @if ($textarea)
            <textarea
                name="{{ $field }}"
                rows="{{ $rows }}"
                @if ($maxlength) maxlength="{{ $maxlength }}" @endif
                placeholder="{{ $label }}"
                class="textarea textarea-bordered w-full @if ($bag->has($field)) textarea-error @endif"
            >{{ old($field) }}</textarea>
        @else
            <input
                type="{{ $type }}"
                name="{{ $field }}"
                value="{{ old($field) }}"
                @if ($maxlength) maxlength="{{ $maxlength }}" @endif
                @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
                placeholder="{{ $label }}"
                class="input input-bordered w-full @if ($bag->has($field)) input-error @endif"
            />
        @endif
    </label>
    <x-lead-form-error :bag="$bag" :field="$field" />
</div>
