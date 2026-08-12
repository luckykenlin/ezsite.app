{{-- The plain-text alternative; see emails/text/new-enquiry.blade.php. --}}
Thanks for getting in touch

Hi {{ $lead->displayName() }}, we have your message and will get back to you as soon as we can.
@if ($lead->isReservation() && $lead->reservationLine())

You asked for {{ $lead->reservationLine() }} — we will confirm your table as soon as we can.
@endif
@if (filled($lead->message))

What you sent us:

{{ $lead->message }}
@endif
@if (filled($identity->phone))

If it is urgent, call us on {{ $identity->phone }}.
@endif

Sent by {{ $identity->siteName }} via {{ config('app.name') }}
