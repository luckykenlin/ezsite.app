<?php

declare(strict_types=1);

namespace App\Templates;

use App\Templates\Definitions\BubbleTea;
use App\Templates\Definitions\BurgerJoint;
use App\Templates\Definitions\ChineseRestaurant;
use App\Templates\Definitions\DesignerPortfolio;
use App\Templates\Definitions\HairStudio;
use App\Templates\Definitions\MassageSpa;
use App\Templates\Definitions\NailSalon;
use App\Templates\Definitions\PersonalResume;
use App\Templates\Definitions\PizzaShop;
use Illuminate\Support\Facades\Lang;

/**
 * The hand-curated industry templates: the whole library, enumerable.
 *
 * A PHP enum for the same reason {@see \App\Design\StylePreset} is one —
 * templates are designed artifacts, not user data. Nothing about a template
 * varies per install, so a row in a table would buy nothing and cost the
 * enumerability that the gallery, the router (`/templates/{template}` binds
 * this enum directly), `demo:seed` and every test iterate over.
 *
 * Each case's page structure lives in its own class under {@see Definitions} —
 * one `match` returning a dozen 250-line literals would be a single unreadable
 * method — and this enum is the index plus the accessors for the
 * gallery-facing prose, which lives under `marketing.templates` in
 * `lang/{locale}/marketing.php`.
 */
enum SiteTemplate: string
{
    case ChineseRestaurant = 'chinese-restaurant';
    case PizzaShop = 'pizza-shop';
    case BurgerJoint = 'burger-joint';
    case BubbleTea = 'bubble-tea';
    case NailSalon = 'nail-salon';
    case HairStudio = 'hair-studio';
    case MassageSpa = 'massage-spa';
    case PersonalResume = 'personal-resume';
    case DesignerPortfolio = 'designer-portfolio';

    /**
     * How many templates the library holds — for the marketing copy that counts
     * them ("Eight finished sites…").
     *
     * Here rather than typed into the three places that said it, because it went
     * stale the first time the library grew: the gallery headline, the home
     * page's steps and the gallery's own meta description all still claimed
     * eight the day a ninth shipped. A number nobody maintains is worse
     * marketing than no number, since it is the one claim on the page a visitor
     * can check by counting the cards underneath it.
     *
     * A numeral, not a spelled-out word, and that is the whole reason this
     * returns an int. `Number::spell()` would read closer to the original copy
     * and costs an undeclared `ext-intl` — which it does not degrade around, it
     * THROWS — so a marketing page would 500 on a box without the extension to
     * avoid writing "9". Declaring the extension for one headline is the tail
     * wagging the dog.
     */
    public static function libraryCount(): int
    {
        return count(self::cases());
    }

    /**
     * The subdomain this template's demo site lives on.
     *
     * The `demo-` prefix is reserved in `config/templates.php`, so no signup
     * can ever collide with one — which is what lets `demo:seed` be a
     * find-or-create rather than a conflict to resolve.
     */
    public function demoSubdomain(): string
    {
        return 'demo-'.$this->value;
    }

    public function definition(): TemplateDefinition
    {
        return match ($this) {
            self::ChineseRestaurant => ChineseRestaurant::definition(),
            self::PizzaShop => PizzaShop::definition(),
            self::BurgerJoint => BurgerJoint::definition(),
            self::BubbleTea => BubbleTea::definition(),
            self::NailSalon => NailSalon::definition(),
            self::HairStudio => HairStudio::definition(),
            self::MassageSpa => MassageSpa::definition(),
            self::PersonalResume => PersonalResume::definition(),
            self::DesignerPortfolio => DesignerPortfolio::definition(),
        };
    }

    /**
     * Plain `label()` rather than Filament's `HasLabel`, deliberately: this is
     * marketing copy for public surfaces, not a panel Select option — the
     * enums the panel renders (PostKind, LeadFieldSet, ...) implement
     * HasLabel::getLabel() instead. Two styles, one rule: HasLabel when a
     * Filament component consumes the case, label() when the site does.
     *
     * Translated, unlike {@see \App\Design\StylePreset::label()}: this text is
     * only ever read by a person on the marketing site. It reaches no AI prompt
     * and no database column — the only thing stored about a template is the
     * enum's own `value` (`tenants.template`).
     */
    public function label(): string
    {
        return (string) __('marketing.templates.'.$this->value.'.label');
    }

    /**
     * One line for the gallery card — what this template is for, in the words
     * the owner of that business would use.
     */
    public function description(): string
    {
        return (string) __('marketing.templates.'.$this->value.'.description');
    }

    /**
     * The three things this template gives a visitor that a generic page does
     * not — the "why this one" list on the template detail page.
     *
     * @return list<string>
     */
    public function highlights(): array
    {
        /** @var list<string> $highlights */
        $highlights = __('marketing.templates.'.$this->value.'.highlights');

        return $highlights;
    }

    /**
     * The wizard question behind one of this template's extra fields.
     *
     * On the enum rather than on TemplateField because a field only knows its
     * own key, and a key means different things in different templates:
     * `service_one` is "your most-booked service" in the hair studio and "the
     * service you are known for" in the nail salon. The template is the half of
     * the pair that can disambiguate.
     */
    public function fieldLabel(TemplateField $field): string
    {
        return (string) __($this->fieldKey($field, 'label'));
    }

    /**
     * The hint under a field, for the handful of questions that need one.
     */
    public function fieldHelp(TemplateField $field): ?string
    {
        $key = $this->fieldKey($field, 'help');

        return Lang::has($key) ? (string) __($key) : null;
    }

    private function fieldKey(TemplateField $field, string $attribute): string
    {
        return sprintf('marketing.templates.%s.fields.%s.%s', $this->value, $field->key, $attribute);
    }
}
