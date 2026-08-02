<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The button an update carries.
 *
 * These six cases are EXACTLY Google Business Profile's six `actionType` values
 * (`BOOK`, `ORDER`, `SHOP`, `LEARN_MORE`, `SIGN_UP`, `CALL`), and the set is
 * closed for that reason: a seventh of our own would be untranslatable the day
 * the connector ships, and Google rejects an unknown action rather than ignoring
 * it. The vocabulary is also good on its own terms — an update with no button is
 * an announcement, and an announcement does not convert.
 *
 * `Call` is the odd one, and the asymmetry is Google's: it has no URL, because
 * the profile's own phone number is used. Sending one anyway is rejected.
 * {@see requiresUrl()} is what keeps the form and the eventual payload honest
 * about that.
 */
enum PostCtaAction: string implements HasLabel
{
    case Book = 'book';
    case Order = 'order';
    case Shop = 'shop';
    case LearnMore = 'learn_more';
    case SignUp = 'sign_up';
    case Call = 'call';

    public function getLabel(): string
    {
        return match ($this) {
            self::Book => __('Book'),
            self::Order => __('Order online'),
            self::Shop => __('Shop'),
            self::LearnMore => __('Learn more'),
            self::SignUp => __('Sign up'),
            self::Call => __('Call now'),
        };
    }

    /**
     * The value Google Business Profile expects in `callToAction.actionType`.
     *
     * Named after the destination rather than derived with `Str::upper()`, so the
     * mapping is a fact this class states rather than a coincidence of our own
     * casing surviving forever.
     */
    public function toGoogleActionType(): string
    {
        return match ($this) {
            self::Book => 'BOOK',
            self::Order => 'ORDER',
            self::Shop => 'SHOP',
            self::LearnMore => 'LEARN_MORE',
            self::SignUp => 'SIGN_UP',
            self::Call => 'CALL',
        };
    }

    public function requiresUrl(): bool
    {
        return $this !== self::Call;
    }
}
