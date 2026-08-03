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

/**
 * The hand-curated industry templates: the whole library, enumerable.
 *
 * A PHP enum for the same reason {@see \App\Design\StylePreset} is one —
 * templates are designed artifacts, not user data. Nothing about a template
 * varies per install, so a row in a table would buy nothing and cost the
 * enumerability that the gallery, the router (`/templates/{template}` binds
 * this enum directly), `demo:seed` and every test iterate over.
 *
 * Each case's copy and page structure live in its own class under
 * {@see Definitions} — one `match` returning a dozen 250-line literals would be
 * a single unreadable method — and this enum is the index plus the
 * gallery-facing prose.
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
     */
    public function label(): string
    {
        return match ($this) {
            self::ChineseRestaurant => 'Chinese restaurant',
            self::PizzaShop => 'Pizza shop',
            self::BurgerJoint => 'Burger joint',
            self::BubbleTea => 'Bubble tea shop',
            self::NailSalon => 'Nail salon',
            self::HairStudio => 'Hair studio',
            self::MassageSpa => 'Massage & spa',
            self::PersonalResume => 'Personal resume',
            self::DesignerPortfolio => 'Designer portfolio',
        };
    }

    /**
     * One line for the gallery card — what this template is for, in the words
     * the owner of that business would use.
     */
    public function description(): string
    {
        return match ($this) {
            self::ChineseRestaurant => 'A menu, a story and a map. Built for a family kitchen that fills up on Friday nights.',
            self::PizzaShop => 'Warm, loud and hungry. The neighbourhood pizzeria that people order from twice a week.',
            self::BurgerJoint => 'Big type, big photographs, no fuss. For a counter with a queue out the door.',
            self::BubbleTea => 'Bright and product-forward, with room for a seasonal menu that changes every month.',
            self::NailSalon => 'A dark room, gold accents and a gallery of your work directly under the fold.',
            self::HairStudio => 'A screenful of type before a single photograph. For a studio whose waiting list is the pitch.',
            self::MassageSpa => 'Airy and unhurried, with one calm way to book. Nothing on the page raises its voice.',
            self::PersonalResume => 'Your name, your work and a way to reach you. Type only — no stock photograph in sight.',
            self::DesignerPortfolio => 'Magazine layout, work in the first viewport. For studios whose portfolio does the selling.',
        };
    }

    /**
     * The three things this template gives a visitor that a generic page does
     * not — the "why this one" list on the template detail page.
     *
     * @return list<string>
     */
    public function highlights(): array
    {
        return match ($this) {
            self::ChineseRestaurant => ['A priced menu section you fill in during signup', 'Address, hours and phone bound to your real location', 'A story page that is already written'],
            self::PizzaShop => ['Three signature pizzas with prices, straight from the form', 'A call-to-order banner on every page', 'Photography chosen for wood-fired warmth'],
            self::BurgerJoint => ['Headline type sized for a photograph, not a paragraph', 'A menu block you can extend to the full board', 'Loud, casual copy you can keep as written'],
            self::BubbleTea => ['A seasonal-specials section built to be swapped monthly', 'Pastel product photography from the shared library', 'A clean geometric grid that survives long drink names'],
            self::NailSalon => ['A nail-art gallery immediately under the hero', 'A priced service menu, filled in during signup', 'The one dark preset in the library, with gold accents'],
            self::HairStudio => ['A full-screen opening in type alone — no stock photograph above the fold', 'A cutting and colour menu with real times and prices', 'The one look in the library whose sections arrive as you scroll'],
            self::MassageSpa => ['A tall, quiet hero with a single call to action', 'A treatment menu with durations and prices', 'Spacing tuned so nothing on the page hurries you'],
            self::PersonalResume => ['A type-only hero: your name, your title, one line', 'Three experience entries you fill in during signup', 'No stock photograph pretending to be you'],
            self::DesignerPortfolio => ['Your work on black, in the first viewport', 'Three project write-ups from the signup form', 'A services page with real engagement shapes and prices'],
        };
    }
}
