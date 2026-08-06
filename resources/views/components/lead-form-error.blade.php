{{--
    One field's validation message, read from the submitting form's OWN error
    bag rather than the default one. Every capture form on a page shares the
    `name`/`email`/`phone` field names, so the default bag would show the
    popup's failure under the contact form's inputs too.
--}}
@props(['bag', 'field'])

@if ($bag->has($field))
    <p class="text-error text-sm">{{ $bag->first($field) }}</p>
@endif
