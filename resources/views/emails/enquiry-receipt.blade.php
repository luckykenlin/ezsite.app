{{-- @see App\Mail\EnquiryReceipt --}}
<x-mail.layout
    :site-name="$identity->siteName"
    :accent="$identity->accent()"
    :preheader="'We have your message and will get back to you soon.'"
>
    <h1 style="margin: 0 0 16px; font-size: 22px; line-height: 30px; font-weight: 700; color: #18181b;">
        Thanks for getting in touch
    </h1>

    <p style="margin: 0 0 20px; font-size: 15px; line-height: 24px; color: #3f3f46;">
        Hi {{ $lead->displayName() }}, we have your message and will get back to you as soon as we can.
    </p>

    @if ($lead->isReservation() && $lead->reservationLine())
        <p style="margin: 0 0 20px; font-size: 15px; line-height: 24px; color: #3f3f46;">
            You asked for <strong style="color: #18181b;">{{ $lead->reservationLine() }}</strong> — we will confirm your table as soon as we can.
        </p>
    @endif

    @if (filled($lead->message))
        <p style="margin: 0 0 8px; font-size: 13px; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; color: #71717a;">What you sent us</p>

        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px;">
            <tr>
                <td style="padding: 16px 20px; background-color: #fafafa; border-left: 3px solid {{ $identity->accent() }}; border-radius: 0 6px 6px 0;">
                    <p style="margin: 0; font-size: 15px; line-height: 24px; color: #3f3f46; white-space: pre-line;">{{ $lead->message }}</p>
                </td>
            </tr>
        </table>
    @endif

    @if (filled($identity->phone))
        <p style="margin: 0; font-size: 15px; line-height: 24px; color: #3f3f46;">
            If it is urgent, call us on <strong style="color: #18181b;">{{ $identity->phone }}</strong>.
        </p>
    @endif
</x-mail.layout>
