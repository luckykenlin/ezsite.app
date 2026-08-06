<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Confirms to the visitor that their enquiry arrived.
 *
 * The public forms already answer with an on-page success message, but a
 * visitor who closes the tab has no record that they ever made contact — and a
 * small business that appears to have swallowed an enquiry loses it to whoever
 * answers next. This is also the first email the site's own domain-less
 * customers ever receive from it, so it echoes their message back: proof the
 * right thing was received, and a thread to reply into.
 *
 * Only sent when the lead left an address; the phone-first capture surfaces
 * (the mobile call bar) produce leads with nothing to send to.
 */
final class EnquiryReceipt extends Mailable
{
    public function __construct(
        private readonly Lead $lead,
        private readonly SiteMailIdentity $identity,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->identity->from(),
            replyTo: $this->identity->replyTo(),
            subject: sprintf('Thanks for contacting %s', $this->identity->siteName),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.enquiry-receipt',
            text: 'emails.text.enquiry-receipt',
            with: [
                'lead' => $this->lead,
                'identity' => $this->identity,
            ],
        );
    }
}
