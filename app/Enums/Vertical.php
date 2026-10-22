<?php

declare(strict_types=1);

namespace App\Enums;

use App\Themes\Theme;
use Filament\Support\Contracts\HasLabel;

/**
 * The kind of business a site is for — the axis the product is organised
 * around since the pivot to vertical small-business sites.
 *
 * A vertical is what makes the same tables read differently: `catalog_items`
 * are "Services" with durations for a nail salon and would be a "Menu" with
 * none for a restaurant; `team_members` are "Technicians" here and "Stylists"
 * elsewhere. Rather than one table per vertical, the structured data is
 * shared and the vertical supplies its vocabulary and rules — every method is
 * a `match`, so adding a case forces a decision for each of them instead of
 * inheriting a nail salon's answer by accident.
 *
 * Distinct from {@see \App\Templates\SiteTemplate} (a starting point a site
 * was built from) and {@see Theme} (how a site looks): a vertical is what the
 * business IS, and constrains the other two.
 */
enum Vertical: string implements HasLabel
{
    case NailSalon = 'nail-salon';

    public function getLabel(): string
    {
        return match ($this) {
            self::NailSalon => __('Nail salon'),
        };
    }

    /**
     * The themes a site of this vertical may render through, the first being
     * the one a new site starts on.
     *
     * @return list<Theme>
     */
    public function themes(): array
    {
        return match ($this) {
            // Only the transitional Default theme exists yet; the nail salon's
            // own theme replaces it here when it ships (P3).
            self::NailSalon => [Theme::Default],
        };
    }

    /**
     * What the business sells, as a heading: the catalogue's plural name.
     */
    public function catalogLabel(): string
    {
        return match ($this) {
            self::NailSalon => __('Services'),
        };
    }

    public function catalogItemLabel(): string
    {
        return match ($this) {
            self::NailSalon => __('Service'),
        };
    }

    public function teamLabel(): string
    {
        return match ($this) {
            self::NailSalon => __('Technicians'),
        };
    }

    public function teamMemberLabel(): string
    {
        return match ($this) {
            self::NailSalon => __('Technician'),
        };
    }

    public function galleryLabel(): string
    {
        return match ($this) {
            self::NailSalon => __('Our work'),
        };
    }

    /**
     * Whether catalogue items carry a duration. True for anything booked by
     * the appointment slot; a restaurant menu item has no duration, and the
     * editor should not ask for one.
     *
     * Derived rather than matched, for now: a vertical sells TIME exactly when
     * customers book it, so "has booking link kinds" is the same question. It
     * also keeps PHPStan quiet — a one-case `match` returning `true` is
     * reported as a return type that should be narrowed to `true`. Turn it into
     * a `match` the day a vertical books without durations (or the reverse).
     */
    public function usesDuration(): bool
    {
        return $this->bookingLinkKinds() !== [];
    }

    /**
     * The schema.org `LocalBusiness` subtype the site's structured data
     * declares — the most specific type Google recognises for the vertical.
     */
    public function schemaOrgType(): string
    {
        return match ($this) {
            self::NailSalon => 'NailSalon',
        };
    }

    /**
     * Which link kinds count as "book now" for this vertical, in the order a
     * site should prefer them when more than one is set.
     *
     * @return list<LinkKind>
     */
    public function bookingLinkKinds(): array
    {
        return match ($this) {
            self::NailSalon => [
                LinkKind::Booksy,
                LinkKind::Vagaro,
                LinkKind::Fresha,
                LinkKind::Square,
                LinkKind::GlossGenius,
                LinkKind::OtherBooking,
            ],
        };
    }
}
