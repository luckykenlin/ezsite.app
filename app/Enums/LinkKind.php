<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Where an outbound link on a small-business site points: a booking platform,
 * a social profile, a review site, an ordering service, or the business's own
 * other website.
 *
 * A closed set rather than a free-text "label + url" pair, because the site
 * does different things with different kinds — a booking kind becomes the
 * "Book now" button, a social kind becomes an icon in the footer, a profile on
 * Google or Yelp becomes a `sameAs` in the schema.org graph. A free-text label
 * would leave every one of those surfaces guessing from the string.
 *
 * The three predicates below are deliberately not a single `category()`: a
 * kind may answer none of them (Website, Yelp, Google) and the callers only
 * ever ask one yes/no question each.
 */
enum LinkKind: string implements HasLabel
{
    case Booksy = 'booksy';
    case Vagaro = 'vagaro';
    case Fresha = 'fresha';
    case Square = 'square';
    case GlossGenius = 'glossgenius';
    case OtherBooking = 'other-booking';
    case Instagram = 'instagram';
    case TikTok = 'tiktok';
    case Facebook = 'facebook';
    case Yelp = 'yelp';
    case Google = 'google';
    case DoorDash = 'doordash';
    case UberEats = 'ubereats';
    case Website = 'website';

    public function getLabel(): string
    {
        return match ($this) {
            self::Booksy => 'Booksy',
            self::Vagaro => 'Vagaro',
            self::Fresha => 'Fresha',
            self::Square => 'Square Appointments',
            self::GlossGenius => 'GlossGenius',
            self::OtherBooking => __('Other booking link'),
            self::Instagram => 'Instagram',
            self::TikTok => 'TikTok',
            self::Facebook => 'Facebook',
            self::Yelp => 'Yelp',
            self::Google => __('Google Business Profile'),
            self::DoorDash => 'DoorDash',
            self::UberEats => 'Uber Eats',
            self::Website => __('Website'),
        };
    }

    /**
     * Whether the link takes a visitor to an appointment booking page — the
     * candidates for a "Book now" button.
     */
    public function isBooking(): bool
    {
        return match ($this) {
            self::Booksy, self::Vagaro, self::Fresha, self::Square, self::GlossGenius, self::OtherBooking => true,
            self::Instagram, self::TikTok, self::Facebook, self::Yelp, self::Google,
            self::DoorDash, self::UberEats, self::Website => false,
        };
    }

    /**
     * Whether the link is a social profile — rendered as an icon, and followed
     * rather than booked or ordered through.
     */
    public function isSocial(): bool
    {
        return match ($this) {
            self::Instagram, self::TikTok, self::Facebook => true,
            self::Booksy, self::Vagaro, self::Fresha, self::Square, self::GlossGenius, self::OtherBooking,
            self::Yelp, self::Google, self::DoorDash, self::UberEats, self::Website => false,
        };
    }

    /**
     * Whether the link is a delivery / takeout ordering service — the
     * restaurant counterpart of a booking link.
     */
    public function isOrdering(): bool
    {
        return match ($this) {
            self::DoorDash, self::UberEats => true,
            self::Booksy, self::Vagaro, self::Fresha, self::Square, self::GlossGenius, self::OtherBooking,
            self::Instagram, self::TikTok, self::Facebook, self::Yelp, self::Google, self::Website => false,
        };
    }
}
