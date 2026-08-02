{{--
    The signup block's lead form, however its section is laid out: both
    variants used to derive the field set, mint the form id and spell out the
    same eight-prop lead-form call independently. This owns the derivation
    — stored block data is untrusted, so `fields` goes through tryFrom — and
    pins the InlineForm source once. Wrapper classes come through the
    attribute bag.

    Deriving the form id in a php block is load-bearing, not style: Blade
    evaluates an anonymous component's attribute expressions twice (once for
    data, once for the attribute bag), so a side effect like the form-id
    counter must run in the body, or every second id is silently skipped.
--}}
@props([
    'page',
    'fields' => null,
    'buttonLabel' => null,
    'successMessage' => null,
    'finePrint' => null,
])
@php
    $formId = resolve(\App\Site\LeadFormIds::class)->next('signup');
    $fieldSet = \App\Enums\LeadFieldSet::tryFrom((string) $fields) ?? \App\Enums\LeadFieldSet::Phone;
@endphp
<x-lead-form
    :form-id="$formId"
    {{ $attributes }}
    :page="$page"
    :source="\App\Enums\LeadSource::InlineForm"
    :fields="$fieldSet"
    :button-label="$buttonLabel"
    :success-message="$successMessage"
    :fine-print="$finePrint"
/>
