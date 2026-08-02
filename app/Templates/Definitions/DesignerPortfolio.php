<?php

declare(strict_types=1);

namespace App\Templates\Definitions;

use App\Design\StylePreset;
use App\Enums\PhotoCategory;
use App\StockPhotos\PhotoOrientation;
use App\Templates\DemoProfile;
use App\Templates\PhotoQuery;
use App\Templates\TemplateDefinition;
use App\Templates\TemplateField;

/**
 * Independent design studio — the media-heavy pilot.
 *
 * The deliberate opposite of {@see ChineseRestaurant}: no location to bind, no
 * prices, and the work itself carrying the page. It is the shape that proves
 * the template DTO is not quietly assuming a shopfront — galleries, a
 * type-only section rhythm, and per-project copy driven by wizard answers
 * rather than a menu.
 *
 * BoldEditorial, unmodified: plum, sharp corners and Impact typography on a
 * compact vertical rhythm — the one preset that treats a dark band as a design
 * element, which is what puts photographs of work on black in the first
 * viewport.
 */
final readonly class DesignerPortfolio
{
    public static function definition(): TemplateDefinition
    {
        return new TemplateDefinition(
            preset: StylePreset::BoldEditorial,
            brandPrimary: '#5B2A63',
            brandSecondary: '#141216',
            brandAccent: '#E4B7FF',
            category: 'design studio',
            chrome: self::chrome(),
            pages: [self::home(), self::about(), self::services()],
            photoQueries: self::photoQueries(),
            demoProfile: self::demoProfile(),
            extraFields: self::extraFields(),
        );
    }

    private static function demoProfile(): DemoProfile
    {
        return new DemoProfile(
            name: 'Ora Studio',
            tagline: 'Brand and product design for people building something difficult',
            description: 'A two-person design studio working on identity, product and the awkward space between them.',
            city: 'Lisbon',
            phone: '+351 21 555 0188',
            email: 'studio@ora.example',
            addressLine1: 'Rua da Boavista 44',
            state: 'Lisboa',
            postalCode: '1200-067',
            country: 'PT',
            timezone: 'Europe/Lisbon',
        );
    }

    /**
     * @return list<TemplateField>
     */
    private static function extraFields(): array
    {
        return [
            new TemplateField('discipline', 'What you actually do', 'Brand and product design', help: 'One line. It goes under your name in the hero.'),
            new TemplateField('project_one', 'A project to lead with', 'Meridian — identity for a climate fund'),
            new TemplateField('project_two', 'A second project', 'Halcyon — product design for a sleep app'),
            new TemplateField('project_three', 'A third project', 'Field Notes — editorial system for a quarterly'),
        ];
    }

    /**
     * @return list<PhotoQuery>
     */
    private static function photoQueries(): array
    {
        return [
            new PhotoQuery('minimal design studio workspace', category: PhotoCategory::Workspace, count: 4),
            new PhotoQuery('graphic design print portfolio spread', category: PhotoCategory::Product, count: 6),
            new PhotoQuery('brand identity mockup stationery', category: PhotoCategory::Product, count: 4),
            new PhotoQuery('designer at work portrait', PhotoOrientation::Portrait, PhotoCategory::People, 3),
        ];
    }

    /**
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    private static function chrome(): array
    {
        return [
            ['type' => 'header', 'data' => [
                'variant' => 'inverted',
                'nav_links' => [
                    ['label' => 'Work', 'url' => '/'],
                    ['label' => 'Studio', 'url' => '/about'],
                    ['label' => 'Services', 'url' => '/services'],
                ],
                'cta_label' => 'Start a project',
                'cta_url' => 'mailto:{email}',
            ]],
            ['type' => 'footer', 'data' => [
                'variant' => 'minimal',
                'nav_links' => [
                    ['label' => 'Studio', 'url' => '/about'],
                    ['label' => 'Services', 'url' => '/services'],
                ],
                'note' => '{business_name} — {city}. Available for new work.',
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function home(): array
    {
        return [
            'slug' => '/',
            'title' => '{business_name}',
            'meta_description' => '{business_name} — {discipline}, from {city}.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'full-bleed-overlay', 'data' => [
                    'eyebrow' => '{city}',
                    'heading' => '{business_name}',
                    'subheading' => '{discipline}. Selected work below.',
                    'cta_label' => 'Start a project',
                    'cta_url' => 'mailto:{email}',
                    'image_query' => 'minimal design studio workspace',
                ]],
                ['type' => 'gallery', 'variant' => 'masonry', 'tone' => 'inverted', 'spacing' => 'tight', 'data' => [
                    'heading' => 'Selected work',
                    'images' => [
                        ['alt' => '{project_one}'],
                        ['alt' => '{project_two}'],
                        ['alt' => '{project_three}'],
                        ['alt' => 'Studio archive'],
                        ['alt' => 'Studio archive'],
                        ['alt' => 'Studio archive'],
                    ],
                    'image_query' => 'graphic design print portfolio spread',
                ]],
                ['type' => 'features', 'variant' => 'alternating', 'tone' => 'base', 'spacing' => 'tight', 'data' => [
                    'heading' => 'Three recent projects',
                    'intro' => 'The short version. The long version is a conversation.',
                    'features' => [
                        ['icon' => '01', 'title' => '{project_one}', 'description' => 'A full identity system, built to survive being used by people who are not designers.'],
                        ['icon' => '02', 'title' => '{project_two}', 'description' => 'Product design end to end — research, interface, and the unglamorous states nobody screenshots.'],
                        ['icon' => '03', 'title' => '{project_three}', 'description' => 'An editorial system that stays recognisable across four issues a year and three contributors.'],
                    ],
                ]],
                ['type' => 'testimonials', 'variant' => 'spotlight', 'tone' => 'inverted', 'data' => [
                    'heading' => 'What it is like to work with us',
                    'testimonials' => [
                        ['quote' => 'They asked better questions than we did, and then answered them in about four weeks.', 'author' => 'Ines Marques', 'role' => 'Founder, Meridian'],
                    ],
                ]],
                ['type' => 'cta', 'variant' => 'banner', 'data' => [
                    'heading' => 'Have something difficult?',
                    'body' => 'We take on three or four projects a year. Tell us about yours.',
                    'cta_label' => 'Email the studio',
                    'cta_url' => 'mailto:{email}',
                    'secondary_label' => 'How we work',
                    'secondary_url' => '/services',
                ]],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function about(): array
    {
        return [
            'slug' => '/about',
            'title' => 'Studio',
            'meta_description' => 'Who {business_name} is, and how the studio works.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'centered-minimal', 'tone' => 'inverted', 'data' => [
                    'eyebrow' => 'Studio',
                    'heading' => 'Small on purpose',
                    'subheading' => 'Two people, a handful of projects a year, and no account layer between you and the work.',
                ]],
                ['type' => 'prose', 'variant' => 'side-heading', 'data' => [
                    'heading' => 'How we got here',
                    'paragraphs' => [
                        ['text' => 'We started {business_name} after years inside larger studios, where the best thinking happened in the first week and got sanded down for the next six months.'],
                        ['text' => 'So the studio is deliberately small. The people you meet are the people who do the work, and the work goes out while it is still sharp.'],
                    ],
                ]],
                ['type' => 'stats', 'tone' => 'inverted', 'data' => [
                    'heading' => 'The studio in four numbers',
                    'stats' => [
                        ['value' => '2', 'label' => 'Designers'],
                        ['value' => '4', 'label' => 'Projects a year'],
                        ['value' => '9', 'label' => 'Years running'],
                    ],
                ]],
                ['type' => 'gallery', 'variant' => 'grid', 'data' => [
                    'heading' => 'The room',
                    'images' => [
                        ['alt' => 'The studio wall mid-project'],
                        ['alt' => 'Proofs on the table'],
                        ['alt' => 'Working through options'],
                    ],
                    'image_query' => 'minimal design studio workspace',
                ]],
                ['type' => 'cta', 'variant' => 'banner', 'data' => [
                    'heading' => 'Want to talk?',
                    'body' => 'Send us a paragraph about the problem. That is enough to start.',
                    'cta_label' => 'Email {email}',
                    'cta_url' => 'mailto:{email}',
                ]],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function services(): array
    {
        return [
            'slug' => '/services',
            'title' => 'Services',
            'meta_description' => 'What {business_name} takes on, how long it takes, and what it costs.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'centered-minimal', 'data' => [
                    'eyebrow' => 'Services',
                    'heading' => 'Three ways to work together',
                    'subheading' => 'Every project is different. These are the three shapes most of them take.',
                ]],
                ['type' => 'pricing', 'tone' => 'base', 'item_style' => 'outline', 'data' => [
                    'heading' => 'Engagements',
                    'intro' => 'Fixed scope, fixed price, no hourly billing. Numbers are starting points.',
                    'plans' => [
                        ['name' => 'Identity', 'price' => 'from €12k', 'period' => '4–6 weeks', 'description' => 'For a company that needs to look like itself.', 'features' => "Naming support\nLogo and type system\nColour and art direction\nGuidelines your team can use", 'cta_label' => 'Enquire', 'cta_url' => 'mailto:{email}'],
                        ['name' => 'Product', 'price' => 'from €20k', 'period' => '8–12 weeks', 'description' => 'For a product that works and does not yet feel like it.', 'features' => "Research and flows\nInterface design\nDesign system\nBuild support", 'cta_label' => 'Enquire', 'cta_url' => 'mailto:{email}', 'is_featured' => true],
                        ['name' => 'Retainer', 'price' => '€4k', 'period' => 'per month', 'description' => 'For teams who need design on tap, not in a lump.', 'features' => "A standing day a week\nDirect access, no tickets\nRolling monthly\nCancel with a month's notice", 'cta_label' => 'Enquire', 'cta_url' => 'mailto:{email}'],
                    ],
                ]],
                ['type' => 'steps', 'variant' => 'timeline', 'tone' => 'muted', 'data' => [
                    'heading' => 'How a project runs',
                    'intro' => 'The same four moves every time, whatever the engagement.',
                    'steps' => [
                        ['title' => 'A conversation', 'description' => 'An hour, no charge. We work out whether the problem is the one you think it is.'],
                        ['title' => 'A written proposal', 'description' => 'Scope, price and dates in one document, so there is nothing to renegotiate later.'],
                        ['title' => 'The work', 'description' => 'Weekly, in the open. You see everything while it is still changeable.'],
                        ['title' => 'Handover', 'description' => 'Files, systems and a walkthrough for whoever picks it up next.'],
                    ],
                ]],
                ['type' => 'faq', 'data' => [
                    'heading' => 'The practical questions',
                    'questions' => [
                        ['question' => 'How far ahead are you booked?', 'answer' => 'Usually six to eight weeks. Occasionally something moves and we can start sooner — worth asking.'],
                        ['question' => 'Do you work remotely?', 'answer' => 'Almost always. We are in {city}, and most of our clients are not.'],
                        ['question' => 'Can you work with our developers?', 'answer' => 'Yes, and we prefer it. Design that never gets built is not design.'],
                    ],
                ]],
            ],
        ];
    }
}
