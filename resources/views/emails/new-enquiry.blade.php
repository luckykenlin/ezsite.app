{{-- @see App\Mail\NewEnquiry --}}
<x-mail.layout
    :site-name="$identity->siteName"
    :accent="$identity->accent()"
    :preheader="$lead->contactLine() ?? $lead->message"
>
    <h1 style="margin: 0 0 16px; font-size: 22px; line-height: 30px; font-weight: 700; color: #18181b;">
        {{ $lead->isReservation() ? 'New reservation request from' : 'New enquiry from' }} {{ $lead->displayName() }}
    </h1>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px; font-size: 15px; line-height: 22px;">
        @foreach (['When' => $lead->reservationLine(), 'Phone' => $lead->phone, 'Email' => $lead->email] as $label => $value)
            @if (filled($value))
                <tr>
                    <td width="72" valign="top" style="padding: 6px 12px 6px 0; color: #71717a;">{{ $label }}</td>
                    <td valign="top" style="padding: 6px 0; color: #18181b; font-weight: 600;">{{ $value }}</td>
                </tr>
            @endif
        @endforeach
        <tr>
            <td width="72" valign="top" style="padding: 6px 12px 6px 0; color: #71717a;">Source</td>
            <td valign="top" style="padding: 6px 0; color: #18181b;">
                {{ $lead->source->getLabel() }}@if ($lead->page !== null) · {{ $lead->page->slug }}@endif
            </td>
        </tr>
    </table>

    @if (filled($lead->message))
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px;">
            <tr>
                <td style="padding: 16px 20px; background-color: #fafafa; border-left: 3px solid {{ $identity->accent() }}; border-radius: 0 6px 6px 0;">
                    {{-- pre-line rather than nl2br: keeps the view free of raw output. --}}
                    <p style="margin: 0; font-size: 15px; line-height: 24px; color: #3f3f46; white-space: pre-line;">{{ $lead->message }}</p>
                </td>
            </tr>
        </table>
    @endif

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 20px;">
        <tr>
            <td style="border-radius: 8px; background-color: {{ $identity->accent() }};">
                <a href="{{ $inboxUrl }}" style="display: inline-block; padding: 12px 22px; font-size: 15px; font-weight: 600; color: #ffffff; text-decoration: none;">Open the inbox</a>
            </td>
        </tr>
    </table>

    <p style="margin: 0; font-size: 14px; line-height: 21px; color: #71717a;">
        @if (filled($lead->email))
            Reply to this email to answer {{ $lead->displayName() }} directly.
        @else
            {{ $lead->displayName() }} left no email address — the phone number above is the only way to reach them.
        @endif
    </p>
</x-mail.layout>
