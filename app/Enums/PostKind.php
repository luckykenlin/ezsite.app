<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * What kind of announcement an update is.
 *
 * The four cases are Google's own local-post types minus the two it will not let
 * an API create — `ALERT` (the COVID-era slot) and `PRODUCT` — plus `Hours`,
 * which is ours. Choosing Google's vocabulary now means the eventual Business
 * Profile connector needs no migration and no translation table: an `Offer` here
 * is an `OFFER` there.
 *
 * `Hours` is deliberately local-only. "Closed Monday for Lunar New Year" is the
 * highest-value thing a local business can say — "are you open" is the query that
 * loses the most customers — and it earns its keep entirely on the tenant's own
 * site, as a notice bar above the header.
 */
enum PostKind: string implements HasColor, HasLabel
{
    case Update = 'update';
    case Offer = 'offer';
    case Event = 'event';
    case Hours = 'hours';

    public function getLabel(): string
    {
        return match ($this) {
            self::Update => __('What\'s new'),
            self::Offer => __('Offer'),
            self::Event => __('Event'),
            self::Hours => __('Opening hours notice'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Update => 'gray',
            self::Offer => 'success',
            self::Event => 'info',
            self::Hours => 'warning',
        };
    }

    /**
     * One line of guidance in the composer, so the operator picks by intent
     * rather than by guessing what the words mean.
     */
    public function hint(): string
    {
        return match ($this) {
            self::Update => __('This week\'s work, a new arrival, anything worth showing.'),
            self::Offer => __('A discount or deal that runs between two dates.'),
            self::Event => __('Something happening on a specific day.'),
            self::Hours => __('A closure or a change of hours. Shows as a notice across your site.'),
        };
    }

    /**
     * Whether the kind is meaningless without a start and an end.
     *
     * True for Offer AND Event, which surprises people: Google requires the
     * `event{}` object — schedule included — for OFFER as much as for EVENT, and
     * omitting it is the commonest 400 in a first implementation. Encoding it
     * here means the form asks for the dates and the connector never has to.
     */
    public function requiresDateRange(): bool
    {
        return match ($this) {
            self::Offer, self::Event => true,
            self::Update, self::Hours => false,
        };
    }

    /**
     * Whether the kind can carry a coupon code and terms.
     */
    public function isOffer(): bool
    {
        return $this === self::Offer;
    }
}
