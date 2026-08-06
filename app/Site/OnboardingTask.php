<?php

declare(strict_types=1);

namespace App\Site;

/**
 * The things a freshly provisioned site still needs from its owner, in the
 * order they matter.
 *
 * Every case is derived from real stored state ({@see \App\Actions\BuildOnboardingProgress}),
 * never from a "dismissed" flag: a checklist that remembers being ticked drifts
 * out of step with the site and becomes something to click past. And every case
 * is something the provisioning pipeline genuinely cannot do on the owner's
 * behalf — a task that is always green teaches operators to ignore the list.
 *
 * `PublishSite` is first because it is the one that loses customers rather than
 * polish: {@see \App\Actions\Templates\ProvisionSiteFromTemplate} deliberately
 * leaves pages in Draft so the owner reviews before going live, and until this
 * existed nothing ever told them the review was outstanding.
 */
enum OnboardingTask: string
{
    case PublishSite = 'publish_site';
    case SiteAddress = 'site_address';
    case PhoneNumber = 'phone_number';
    case Logo = 'logo';
    case CaptureSurface = 'capture_surface';

    /**
     * Plain `label()` rather than Filament's `HasLabel`, deliberately: this is
     * marketing copy for public surfaces, not a panel Select option — the
     * enums the panel renders (PostKind, LeadFieldSet, ...) implement
     * HasLabel::getLabel() instead. Two styles, one rule: HasLabel when a
     * Filament component consumes the case, label() when the site does.
     */
    public function label(): string
    {
        return match ($this) {
            self::PublishSite => __('Publish your site'),
            self::SiteAddress => __('Add your street address'),
            self::PhoneNumber => __('Check the phone number on your site'),
            self::Logo => __('Upload your logo'),
            self::CaptureSurface => __('Switch on a way to collect enquiries'),
        };
    }

    /**
     * Why it is worth doing — shown only while the task is outstanding.
     *
     * A checklist without reasons is a chore list. Each line names the
     * consequence of leaving it, because that is what makes an owner act.
     */
    public function why(): string
    {
        return match ($this) {
            self::PublishSite => __('Your pages are only visible in here. Anyone who follows a link to your site gets a "page not found" until you publish.'),
            self::SiteAddress => __('Your template left this blank on purpose — its address belonged to an invented business. Google needs yours before it will show you in local results.'),
            self::PhoneNumber => __('Your template left this blank on purpose — its number belonged to an invented business. Visitors who would rather call than write need yours.'),
            self::Logo => __('Until you add one, your header and your share previews fall back to your business name set in type.'),
            self::CaptureSurface => __('The offer popup and the sticky mobile call bar are both switched off. Each one gives a visitor who is not ready to phone a second way to reach you.'),
        };
    }
}
