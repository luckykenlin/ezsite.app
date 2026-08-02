<?php

declare(strict_types=1);

namespace App\Mail;

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
    /**
     * The accent used when a site has no brand colour of its own — the tenant
     * panel's primary, so an unbranded site still looks like part of the product.
     */
    public const string DEFAULT_ACCENT = '#059669';

    public function __construct(
        public string $siteName,
        private string $accent = self::DEFAULT_ACCENT,
        public ?string $replyToEmail = null,
        public ?string $phone = null,
    ) {}

    /**
     * The accent, guaranteed to be a six-digit CSS hex colour.
     *
     * This value reaches an inline `style` attribute, and its source
     * (`businesses.brand_primary`) is written by operators AND by the AI draft
     * pipeline. Validating once here means the email views never have to think
     * about it; Blade's escaping alone would keep the attribute intact but
     * would happily emit a broken colour.
     */
    public function accent(): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $this->accent) === 1
            ? $this->accent
            : self::DEFAULT_ACCENT;
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
