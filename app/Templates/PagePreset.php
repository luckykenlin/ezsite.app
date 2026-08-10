<?php

declare(strict_types=1);

namespace App\Templates;

use App\Templates\PagePresets\About;
use App\Templates\PagePresets\Contact;
use App\Templates\PagePresets\Faqs;
use App\Templates\PagePresets\Gallery;
use App\Templates\PagePresets\Home;
use App\Templates\PagePresets\Portfolio;
use App\Templates\PagePresets\Services;
use App\Templates\PagePresets\Team;
use Filament\Support\Icons\Heroicon;

/**
 * The page-type presets behind the site canvas's "Add a page" picker: a
 * designed starting layout per kind of page, enumerable.
 *
 * A PHP enum for the same reason {@see SiteTemplate} is one — presets are
 * designed artifacts, not user data. The difference is granularity: a
 * template is a whole site pointed at one trade, a preset is ONE page kind
 * (About, Contact, ...) written for any trade, with the same `{business_name}`
 * placeholder mechanism personalizing the copy at creation time.
 *
 * Each case's blocks live in their own class under {@see PagePresets},
 * mirroring {@see Definitions} — one `match` returning eight page literals
 * would be a single unreadable method. `Blank` is a real case rather than a
 * null: the picker renders it as the first card, and "no starting blocks" is
 * a choice the operator makes, not the absence of one.
 *
 * `label()`/`description()` are `__()` literals rather than lang keys because
 * these render only in the tenant panel, where every string follows that
 * convention (unlike SiteTemplate's marketing prose).
 */
enum PagePreset: string
{
    case Blank = 'blank';
    case Home = 'home';
    case About = 'about';
    case Services = 'services';
    case Contact = 'contact';
    case Team = 'team';
    case Faqs = 'faqs';
    case Gallery = 'gallery';
    case Portfolio = 'portfolio';

    public function definition(): PagePresetDefinition
    {
        return match ($this) {
            self::Blank => new PagePresetDefinition(__('Untitled page'), null, []),
            self::Home => Home::definition(),
            self::About => About::definition(),
            self::Services => Services::definition(),
            self::Contact => Contact::definition(),
            self::Team => Team::definition(),
            self::Faqs => Faqs::definition(),
            self::Gallery => Gallery::definition(),
            self::Portfolio => Portfolio::definition(),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Blank => __('Blank'),
            self::Home => __('Home'),
            self::About => __('About'),
            self::Services => __('Services'),
            self::Contact => __('Contact'),
            self::Team => __('Team'),
            self::Faqs => __('FAQs'),
            self::Gallery => __('Gallery'),
            self::Portfolio => __('Portfolio'),
        };
    }

    /**
     * One line for the picker card. Home's is careful NOT to promise it
     * becomes the homepage: it lands as a draft at a slug derived from its
     * title like every other page, and the operator promotes it via Page
     * settings if that is the intent.
     */
    public function description(): string
    {
        return match ($this) {
            self::Blank => __('Start from nothing and build the page block by block.'),
            self::Home => __('A landing-page layout — headline, highlights, social proof and a call to action.'),
            self::About => __('Tell the story of the business: who you are, the numbers, the people.'),
            self::Services => __('What you offer, with prices, how it works, and a push to book.'),
            self::Contact => __('An enquiry form with your address and hours, plus before-you-visit answers.'),
            self::Team => __('Introduce the people a customer will actually deal with.'),
            self::Faqs => __('Answer the questions you get asked every week, once.'),
            self::Gallery => __('Let the photographs do the talking.'),
            self::Portfolio => __('Selected work, presented quietly and confidently.'),
        };
    }

    /**
     * The Blank card's tile, and the fallback when a thumbnail cannot render.
     */
    public function icon(): Heroicon
    {
        return match ($this) {
            self::Blank => Heroicon::OutlinedDocument,
            self::Home => Heroicon::OutlinedHome,
            self::About => Heroicon::OutlinedIdentification,
            self::Services => Heroicon::OutlinedWrenchScrewdriver,
            self::Contact => Heroicon::OutlinedEnvelope,
            self::Team => Heroicon::OutlinedUserGroup,
            self::Faqs => Heroicon::OutlinedQuestionMarkCircle,
            self::Gallery => Heroicon::OutlinedPhoto,
            self::Portfolio => Heroicon::OutlinedRectangleStack,
        };
    }

    /**
     * Whether the picker renders a live preview iframe for this case — only
     * Blank has nothing to show.
     */
    public function hasThumbnail(): bool
    {
        return $this !== self::Blank;
    }
}
