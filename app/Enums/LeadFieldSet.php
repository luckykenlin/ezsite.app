<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Which inputs a capture form asks for.
 *
 * Every field costs completions, so the right set is a marketing decision, not
 * a layout one: a popup trading a discount for an email should ask for the
 * email and nothing else, while the contact page — reached by someone who came
 * specifically to enquire — can afford four fields and gets a better-qualified
 * lead for them.
 *
 * The server does NOT trust this to decide validation. It only decides what to
 * render; `StoreLeadController` independently requires at least one reply
 * channel, so a tampered form can't produce an unanswerable lead.
 */
enum LeadFieldSet: string implements HasLabel
{
    case Email = 'email';

    case Phone = 'phone';

    case EmailPhone = 'email_phone';

    case NameEmail = 'name_email';

    case NamePhone = 'name_phone';

    case Full = 'full';

    public function showsName(): bool
    {
        return in_array($this, [self::NameEmail, self::NamePhone, self::Full], true);
    }

    public function showsEmail(): bool
    {
        return in_array($this, [self::Email, self::EmailPhone, self::NameEmail, self::Full], true);
    }

    public function showsPhone(): bool
    {
        return in_array($this, [self::Phone, self::EmailPhone, self::NamePhone, self::Full], true);
    }

    public function showsMessage(): bool
    {
        return $this === self::Full;
    }

    /**
     * Whether the visible inputs fit on one line, which is what lets the
     * inline signup variants render as a single row.
     */
    public function isSingleLine(): bool
    {
        return ! $this->showsMessage();
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Email => __('Email only'),
            self::Phone => __('Phone only'),
            self::EmailPhone => __('Email and phone'),
            self::NameEmail => __('Name and email'),
            self::NamePhone => __('Name and phone'),
            self::Full => __('Name, phone, email and message'),
        };
    }
}
