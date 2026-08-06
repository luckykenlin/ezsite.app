<?php

declare(strict_types=1);

namespace App\Mail;

use App\Design\ColorPalette;
use Illuminate\Mail\Mailables\Address;

/**
 * Who a tenant site IS inside an email: the name that shows in the inbox, the
 * accent its buttons take, and where a reply to the site goes.
 *
 * It exists because the `from` address is deliberately NOT the tenant's own.
 * Mail leaves on our verified sending domain (`mail.from.address`) or SPF/DKIM
 * fails and the message lands in spam — the one outcome this whole feature
 * exists to prevent. The tenant's identity therefore rides in the display NAME
 * and the Reply-To header instead, and that rule lives here rather than being
 * re-decided inside every Mailable.
 *
 * @see \App\Actions\BuildSiteMailIdentity resolves one for the current tenant
 */
final readonly class SiteMailIdentity
{
    public function __construct(
        public string $siteName,
        private string $accent = ColorPalette::DEFAULT_BRAND,
        public ?string $replyToEmail = null,
        public ?string $phone = null,
    ) {}

    /**
     * The accent, guaranteed to be a six-digit CSS hex colour.
     *
     * This value reaches an inline `style` attribute, and its source
     * (`businesses.brand_primary`) is written by operators AND by the AI draft
     * pipeline. Through {@see ColorPalette::brandOrDefault()} rather than a
     * second copy of the regex: ColorPalette is the documented single gate for
     * every path a brand colour takes into a stylesheet.
     */
    public function accent(): string
    {
        return ColorPalette::brandOrDefault($this->accent);
    }

    /**
     * The sender: our domain, the tenant's name.
     */
    public function from(): Address
    {
        return new Address(config()->string('mail.from.address'), $this->siteName);
    }

    /**
     * Where a reply to the SITE goes — the business's own contact address.
     *
     * Only for mail addressed to the public ({@see EnquiryReceipt}). Mail
     * addressed to the operator replies to the enquirer instead, which is that
     * Mailable's own decision.
     *
     * @return list<Address>
     */
    public function replyTo(): array
    {
        if ($this->replyToEmail === null) {
            return [];
        }

        return [new Address($this->replyToEmail, $this->siteName)];
    }
}
