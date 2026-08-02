<?php

declare(strict_types=1);

namespace App\Site;

use App\Enums\LeadFieldSet;
use App\Enums\PopupTrigger;
use App\Models\Post;

/**
 * The tenant's site-wide capture surfaces: the offer popup and the sticky
 * mobile call bar, read out of `site_settings.capture`.
 *
 * Every accessor here is fail-SAFE. The column is tenant-authored JSON that
 * reaches a `class` attribute, a `data-` attribute and a `tel:` href, so it is
 * parsed with `tryFrom` and clamped rather than trusted — the same trust
 * asymmetry {@see Blocks\SectionLayout} applies to layout axes. Anything
 * unreadable degrades to "switched off", which is the safe direction: a
 * missing popup is a lost opportunity, a malformed one is a broken site.
 *
 * Request-scoped alongside {@see SiteChrome}, so the settings row is read once
 * per render whichever of the two asks for it first.
 */
final readonly class SiteCapture
{
    /**
     * Clamps for the trigger's numeric value. A "0 second" delay fires before
     * the page has painted, and a 400% scroll threshold never fires at all —
     * both read as the feature being broken.
     */
    private const int MIN_DELAY_SECONDS = 1;

    private const int MAX_DELAY_SECONDS = 120;

    private const int MIN_SCROLL_PERCENT = 5;

    private const int MAX_SCROLL_PERCENT = 100;

    public function __construct(private SiteSettingsLoader $settingsLoader)
    {
        //
    }

    public function popupEnabled(): bool
    {
        return $this->bool('popup', 'enabled') && $this->popupHeading() !== '';
    }

    /**
     * The heading, taken from the site's current OFFER update when the operator has
     * asked for that.
     *
     * Derived at read time, never written into `site_settings`. The offer appears
     * when its window opens and disappears when it closes, with nothing to switch
     * off and nothing to drift out of step — which is also the documented house rule
     * for factual data (docs/business-data-model.md): referenced, never copied.
     *
     * OPT-IN, because silently rewriting a popup somebody configured is a surprise,
     * and because a salon may well want a standing "book a consultation" popup that
     * outlives any one deal.
     */
    public function popupHeading(): string
    {
        return $this->currentOffer()->title ?? $this->string('popup', 'heading');
    }

    public function popupOffer(): string
    {
        $offer = $this->currentOffer();

        if (! $offer instanceof Post) {
            return $this->string('popup', 'offer');
        }

        // The coupon is worth more than the prose here: a popup that says
        // "use SPRING10" is a reason to hand over an email address.
        return filled($offer->offer_coupon_code)
            ? mb_trim(sprintf('%s %s', $offer->excerpt ?? '', __('Use code :code.', ['code' => $offer->offer_coupon_code])))
            : ($offer->excerpt ?? $this->string('popup', 'offer'));
    }

    public function popupButtonLabel(): string
    {
        $label = $this->string('popup', 'button_label');

        return $label === '' ? __('Get it') : $label;
    }

    public function popupSuccessMessage(): string
    {
        return $this->string('popup', 'success_message');
    }

    public function popupFinePrint(): string
    {
        return $this->currentOffer()->offer_terms ?? $this->string('popup', 'fine_print');
    }

    /**
     * Whether the operator asked the popup to follow their latest offer.
     */
    public function popupFollowsOffer(): bool
    {
        return $this->bool('popup', 'follow_offer');
    }

    public function popupFields(): LeadFieldSet
    {
        return LeadFieldSet::tryFrom($this->string('popup', 'fields')) ?? LeadFieldSet::Email;
    }

    public function popupTrigger(): PopupTrigger
    {
        return PopupTrigger::tryFrom($this->string('popup', 'trigger')) ?? PopupTrigger::Delay;
    }

    /**
     * The trigger's threshold, clamped into a range that actually fires.
     */
    public function popupTriggerValue(): int
    {
        $trigger = $this->popupTrigger();

        if ($trigger === PopupTrigger::ExitIntent) {
            // A gesture, not a threshold: there is no stored number to honour.
            return 0;
        }

        $value = $this->int('popup', 'trigger_value') ?? $trigger->defaultValue();

        [$min, $max] = $trigger === PopupTrigger::Delay
            ? [self::MIN_DELAY_SECONDS, self::MAX_DELAY_SECONDS]
            : [self::MIN_SCROLL_PERCENT, self::MAX_SCROLL_PERCENT];

        return $this->clamp($value, $min, $max);
    }

    /**
     * How long to leave a visitor alone after they have seen it. Zero means
     * "every page load", which is the operator's call to make.
     */
    public function popupFrequencyDays(): int
    {
        return $this->clamp($this->int('popup', 'frequency_days') ?? 7, 0, 365);
    }

    public function callBarEnabled(): bool
    {
        return $this->bool('call_bar', 'enabled');
    }

    public function callBarLabel(): string
    {
        $label = $this->string('call_bar', 'label');

        return $label === '' ? __('Call now') : $label;
    }

    /**
     * Whether the bar also offers a button that opens the popup — only
     * meaningful when there is a popup to open.
     */
    public function callBarOffersPopup(): bool
    {
        return $this->bool('call_bar', 'show_popup_button') && $this->popupEnabled();
    }

    /**
     * The offer the popup should be advertising, or null when it should stick to
     * what the operator typed.
     *
     * Deliberately NOT memoized here, even though three accessors ask: this class is
     * `final readonly` and has no business holding mutable state, and it does not
     * need to — {@see PostFeed} IS the memo, deriving every answer from one windowed
     * read per request. Three calls here are three in-memory filters.
     */
    private function currentOffer(): ?Post
    {
        return $this->popupFollowsOffer() ? resolve(PostFeed::class)->currentOffer() : null;
    }

    private function clamp(int $value, int $min, int $max): int
    {
        return max($min, min($value, $max));
    }

    private function string(string $section, string $key): string
    {
        $value = $this->value($section, $key);

        return is_string($value) ? mb_trim($value) : '';
    }

    private function bool(string $section, string $key): bool
    {
        return $this->value($section, $key) === true;
    }

    private function int(string $section, string $key): ?int
    {
        $value = $this->value($section, $key);

        if (is_int($value)) {
            return $value;
        }

        // Filament stores a TextInput's numeric value as a string.
        return is_string($value) && ctype_digit($value) ? (int) $value : null;
    }

    private function value(string $section, string $key): mixed
    {
        $capture = $this->settingsLoader->get()?->capture;

        if (! is_array($capture)) {
            return null;
        }

        $group = $capture[$section] ?? null;

        return is_array($group) ? ($group[$key] ?? null) : null;
    }
}
