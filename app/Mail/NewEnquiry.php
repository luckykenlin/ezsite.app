<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Tells the operator an enquiry landed — the email the product's core promise
 * ("you will get customers") depends on. Until this existed the only signal was
 * a bell in a panel nobody logs into between enquiries.
 *
 * Sent one per member rather than one email with several recipients: operators
 * of the same site should not learn each other's addresses from a To header.
 */
final class NewEnquiry extends Mailable
{
    public function __construct(
        private readonly Lead $lead,
        private readonly SiteMailIdentity $identity,
        private readonly string $inboxUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->identity->from(),
            replyTo: $this->enquirerAddress(),
            // The site name is already the sender's display name, so repeating
            // it here would only push the part that identifies the enquiry out
            // of a phone's subject preview.
            subject: sprintf('New enquiry from %s', $this->lead->displayName()),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-enquiry',
            text: 'emails.text.new-enquiry',
            with: [
                'lead' => $this->lead,
                'identity' => $this->identity,
                'inboxUrl' => $this->inboxUrl,
            ],
        );
    }

    /**
     * Reply goes to the ENQUIRER, not back to the site.
     *
     * The operator's fastest possible next move is hitting Reply on their
     * phone, and that has to reach the customer — the single detail that
     * decides whether this email converts or just informs. A lead captured
     * through a phone-only surface has no address to reply to, and then the
     * header is simply absent.
     *
     * Not named `replyTo()`: that is one of Mailable's own public builder
     * methods, and shadowing it privately would break every caller that sets a
     * reply-to the ordinary way.
     *
     * @return list<Address>
     */
    private function enquirerAddress(): array
    {
        if (blank($this->lead->email)) {
            return [];
        }

        return [new Address($this->lead->email, $this->lead->displayName())];
    }
}
