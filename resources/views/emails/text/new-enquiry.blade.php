{{--
    The plain-text alternative. Not decoration: a multipart/alternative message
    scores better with spam filters than an HTML-only one, and this is the copy
    a watch or a screen reader reads out.
--}}
{{ $lead->isReservation() ? 'New reservation request from' : 'New enquiry from' }} {{ $lead->displayName() }}
@if ($lead->reservationLine())

When: {{ $lead->reservationLine() }}
@endif
@if (filled($lead->phone))

Phone: {{ $lead->phone }}
@endif
@if (filled($lead->email))

Email: {{ $lead->email }}
@endif

Source: {{ $lead->source->getLabel() }}@if ($lead->page !== null) ({{ $lead->page->slug }})@endif

@if (filled($lead->message))

{{ $lead->message }}
@endif

Open the inbox: {{ $inboxUrl }}

@if (filled($lead->email))
Reply to this email to answer {{ $lead->displayName() }} directly.
@else
{{ $lead->displayName() }} left no email address — the phone number above is the only way to reach them.
@endif

Sent by {{ $identity->siteName }} via {{ config('app.name') }}
