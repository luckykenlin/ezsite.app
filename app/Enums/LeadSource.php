<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Which capture surface produced an enquiry.
 *
 * The `leads.source` column has existed since the table was created, with
 * `contact_form` as its default and a migration comment anticipating "a future
 * channel"; nothing ever wrote a second value, because there was only ever one
 * form. Now there are several, and the operator's first question about a lead
 * is which surface earned it — a popup that produces nothing but interruption
 * should be turned off, and that judgement needs data.
 *
 * `ContactForm` keeps the historical string so the column default stays valid.
 */
enum LeadSource: string implements HasColor, HasLabel
{
    case ContactForm = 'contact_form';
    case InlineForm = 'inline_form';
    case Popup = 'popup';
    case Reservation = 'reservation';

    public function getLabel(): string
    {
        return match ($this) {
            self::ContactForm => __('Contact form'),
            self::InlineForm => __('Inline form'),
            self::Popup => __('Popup'),
            self::Reservation => __('Reservation'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ContactForm => 'primary',
            self::InlineForm => 'info',
            self::Popup => 'warning',
            self::Reservation => 'success',
        };
    }
}
