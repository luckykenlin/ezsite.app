<?php

declare(strict_types=1);

namespace App\Templates\Definitions;

use App\Enums\PhotoCategory;
use App\StockPhotos\PhotoOrientation;
use App\Templates\DemoProfile;
use App\Templates\PhotoQuery;
use App\Templates\TemplateDefinition;
use App\Templates\TemplateField;
use App\Themes\Definitions\NailLuxe;
use App\Themes\Theme;

/**
 * Independent nail studio — the first template on a vertical theme.
 *
 * It renders through {@see Theme::NailLuxe} rather than the default theme: a
 * blush-and-plum boutique with arched photographs, a serif headline whose key
 * phrase is set in italic (`*like this*` in a heading), pill buttons, a price
 * list on hairline rules and a dark rounded footer card. The template used to
 * borrow the default theme's dark NightLounge scheme, which made the studio
 * look like a cocktail bar; the vertical theme is what lets it look like the
 * trade it is.
 *
 * The home page runs the order a client decides in: the arched opening, the
 * studio's credentials (this theme's own block), the menu as category cards,
 * recent sets, what clients said, then the offer band. The full price list
 * lives on /services.
 */
final readonly class NailSalon
{
    public static function definition(): TemplateDefinition
    {
        return new TemplateDefinition(
            scheme: NailLuxe::BLUSH_PLUM,
            brandPrimary: '#6E1E3A',
            brandSecondary: '#F7EDE8',
            brandAccent: '#C9708A',
            category: 'nail salon',
            chrome: self::chrome(),
            pages: [self::home(), self::services(), self::contact()],
            photoQueries: self::photoQueries(),
            demoProfile: self::demoProfile(),
            extraFields: self::extraFields(),
            theme: Theme::NailLuxe,
        );
    }

    private static function demoProfile(): DemoProfile
    {
        return new DemoProfile(
            name: 'The Lacquer Room',
            tagline: 'Hand-painted nails, one chair at a time',
            description: 'A six-chair nail studio doing structured gel, freehand art and unhurried appointments — low light in the room, colour-true light at the chair.',
            city: 'Savannah',
            phone: '(912) 555-0184',
            email: 'hello@lacquerroom.example',
            addressLine1: '41 Whitaker Street',
            state: 'GA',
            postalCode: '31401',
            timezone: 'America/New_York',
        );
    }

    /**
     * @return list<TemplateField>
     */
    private static function extraFields(): array
    {
        return [
            new TemplateField('service_one', 'Structured gel manicure'),
            new TemplateField('service_one_price', '$65'),
            new TemplateField('service_two', 'Hand-painted art set'),
            new TemplateField('service_two_price', '$95'),
            new TemplateField('service_three', 'Dry pedicure'),
            new TemplateField('service_three_price', '$75'),
        ];
    }

    /**
     * @return list<PhotoQuery>
     */
    private static function photoQueries(): array
    {
        return [
            new PhotoQuery('nail art manicure close up', category: PhotoCategory::Product, count: 6),
            new PhotoQuery('nail salon interior calm', category: PhotoCategory::Interior, count: 4),
            new PhotoQuery('manicure hands soft light', category: PhotoCategory::People, count: 4),
            new PhotoQuery('manicure pastel nails', category: PhotoCategory::Product, count: 3),
            new PhotoQuery('nail polish bottles pastel', category: PhotoCategory::Product, count: 2),
            new PhotoQuery('nail technician portrait salon', PhotoOrientation::Portrait, PhotoCategory::People, 3),
        ];
    }

    /**
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    private static function chrome(): array
    {
        return [
            ['type' => 'header', 'data' => [
                'variant' => 'studio',
                'nav_links' => [
                    ['label' => 'Home', 'url' => '/'],
                    ['label' => 'Services', 'url' => '/services'],
                    ['label' => 'Visit', 'url' => '/contact'],
                ],
                'cta_label' => 'Book',
                'cta_url' => '/contact',
            ]],
            ['type' => 'footer', 'data' => [
                'variant' => 'card',
                'nav_links' => [
                    ['label' => 'Services', 'url' => '/services'],
                    ['label' => 'Visit', 'url' => '/contact'],
                ],
                'note' => '{business_name} — {city}. The book opens a week at a time.',
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
            'meta_description' => '{business_name} — {tagline}. Structured gel and freehand nail art in {city}, by appointment.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'arch', 'data' => [
                    'eyebrow' => 'Nail studio · {city}',
                    'heading' => 'Nails, painted *slowly* and by hand',
                    'subheading' => 'Six chairs, ninety-minute appointments, and a lamp bright enough to catch every edge. No double-booking, no rushing the cuticle work.',
                    'cta_label' => 'Book an appointment',
                    'cta_url' => '/contact',
                    'image_query' => 'manicure hands soft light',
                ]],
                ['type' => 'credentials', 'data' => [
                    'eyebrow' => 'Why clients stay',
                    'heading' => 'Rooted in *calm*',
                    'figures' => [
                        ['value' => '4.9', 'label' => 'Average rating, two hundred reviews'],
                        ['value' => '6', 'label' => 'Chairs, never double-booked'],
                        ['value' => '90', 'label' => 'Minutes for every set'],
                    ],
                    'quote' => 'I opened the studio I wanted to sit in: quiet, unhurried, and honest about what will still look good in week three.',
                    'quote_author' => 'Mara Ellison',
                    'quote_role' => 'Founder, {business_name}',
                    'image_query' => 'nail salon interior calm',
                ]],
                ['type' => 'offerings', 'variant' => 'category-cards', 'data' => [
                    'heading' => 'The *menu*',
                    'intro' => 'Hands, feet and everything painted. Every price is the price of the appointment, not the opening bid.',
                    'items' => [
                        ['group' => 'Hands', 'name' => '{service_one}', 'price' => '{service_one_price}'],
                        ['group' => 'Hands', 'name' => 'Classic manicure', 'price' => '$40'],
                        ['group' => 'Hands', 'name' => 'Removal and reset', 'price' => '$20'],
                        ['group' => 'Feet', 'name' => '{service_three}', 'price' => '{service_three_price}'],
                        ['group' => 'Feet', 'name' => 'Gel pedicure', 'price' => '$70'],
                        ['group' => 'Art', 'name' => '{service_two}', 'price' => '{service_two_price}'],
                        ['group' => 'Art', 'name' => 'Chrome and foil', 'price' => '$15'],
                        ['group' => 'Art', 'name' => 'Accent nails', 'price' => '$10'],
                    ],
                ]],
                ['type' => 'gallery', 'variant' => 'arch-grid', 'data' => [
                    'heading' => 'Recent *sets*',
                    'images' => [
                        ['alt' => 'Milky pink almond set with a gold cuff ring'],
                        ['alt' => 'Micro-French in oxblood'],
                        ['alt' => 'Hand-painted florals on a nude base'],
                    ],
                    'image_query' => 'nail art manicure close up',
                ]],
                ['type' => 'testimonials', 'variant' => 'cards', 'data' => [
                    'heading' => 'Why they keep *coming back*',
                    'testimonials' => [
                        ['quote' => 'Three weeks in and not one lift. I keep checking the edges, and they keep being fine.', 'author' => 'Priya N.', 'role' => 'In every month since 2023'],
                        ['quote' => 'I asked for something loud for a wedding and got the exact shade that was in my head, which has genuinely never happened to me before.', 'author' => 'Dominique R.', 'role' => 'Bride, June'],
                    ],
                ]],
                ['type' => 'cta', 'variant' => 'offer', 'data' => [
                    'heading' => 'Your first set, *fifteen off*',
                    'body' => 'New to the studio? Mention the website when you book and your first appointment is fifteen percent off.',
                    'cta_label' => 'Book now',
                    'cta_url' => '/contact',
                    'secondary_label' => 'See the full menu',
                    'secondary_url' => '/services',
                    'image_query' => 'manicure pastel nails',
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
            'meta_description' => 'Manicures, pedicures and nail art at {business_name} in {city}, with prices and how long each appointment takes.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'editorial', 'data' => [
                    'eyebrow' => 'Services & prices',
                    'heading' => 'What a chair *gets you*',
                    'subheading' => 'Every price below is the price of the appointment, not the opening bid. Art is quoted at the chair before anything is painted.',
                    'cta_label' => 'Book an appointment',
                    'cta_url' => '/contact',
                ]],
                ['type' => 'offerings', 'variant' => 'price-list', 'data' => [
                    'heading' => 'The *menu*',
                    'intro' => "Removal of our own work is included. Removal of somebody else's is fifteen dollars and no commentary.",
                    'items' => [
                        ['group' => 'Manicures', 'name' => '{service_one}', 'price' => '{service_one_price}', 'description' => 'Ninety minutes. Cuticle work under magnification and a built apex that grows out flat.'],
                        ['group' => 'Manicures', 'name' => 'Classic manicure', 'price' => '$40'],
                        ['group' => 'Manicures', 'name' => 'Builder gel overlay', 'price' => '$60'],
                        ['group' => 'Manicures', 'name' => 'Removal and reset', 'price' => '$20'],
                        ['group' => 'Pedicures', 'name' => '{service_three}', 'price' => '{service_three_price}', 'description' => 'No basin and no soaking — dry work holds longer. Callus work included.'],
                        ['group' => 'Pedicures', 'name' => 'Gel pedicure', 'price' => '$70'],
                        ['group' => 'Nail art', 'name' => '{service_two}', 'price' => 'from {service_two_price}', 'description' => 'Line work, chrome, foil or hand-painted — priced by what is on the nail, agreed before we start.'],
                        ['group' => 'Nail art', 'name' => 'Chrome or foil', 'price' => '$15'],
                        ['group' => 'Nail art', 'name' => 'Accent nails', 'price' => '$10'],
                        ['group' => 'Nail art', 'name' => 'French tips', 'price' => '$12'],
                    ],
                ]],
                ['type' => 'faq', 'data' => [
                    'heading' => 'Asked at the *desk*',
                    'intro' => 'The questions that come up before a first appointment.',
                    'questions' => [
                        ['question' => 'Do you take walk-ins?', 'answer' => 'Rarely, and only when somebody cancels. Call {phone} and ask — Tuesday afternoons are the likeliest.'],
                        ['question' => "Can you fill another salon's set?", 'answer' => 'We will take it off and start clean instead. There is no way to know what is underneath, and we would rather own the whole thing.'],
                        ['question' => 'How long does a set actually last?', 'answer' => 'Three to four weeks before a fill, longer if you are gentle with them. Anything lifting inside two weeks is our problem to fix, free.'],
                        ['question' => 'Do you use a drill?', 'answer' => 'On product, yes. On your natural nail, never — that is where the thinning and the sensitivity come from.'],
                    ],
                ]],
                ['type' => 'cta', 'variant' => 'offer', 'data' => [
                    'heading' => 'The book opens *on Sundays*',
                    'body' => 'Appointments go up a week at a time. Call and we will find you a chair — or a cancellation, which comes up more often than you would think.',
                    'cta_label' => 'Call {phone}',
                    'cta_url' => 'tel:{phone}',
                    'image_query' => 'nail polish bottles pastel',
                ]],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function contact(): array
    {
        return [
            'slug' => '/contact',
            'title' => 'Visit',
            'meta_description' => 'Address, hours and booking phone for {business_name} in {city}.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'editorial', 'data' => [
                    'eyebrow' => '{city}',
                    'heading' => 'Book a *chair*',
                    'subheading' => 'The phone is answered between clients, so leave a message if it rings out. Everybody gets called back the same day.',
                ]],
                ['type' => 'contact', 'tone' => 'base', 'spacing' => 'airy', 'data' => [
                    'heading' => 'Send a *message*',
                    'intro' => 'Street parking is free after six. If the door is locked we are mid-appointment — buzz once and give us a minute.',
                    'show_form' => true,
                    'success_message' => 'Thank you — we will call you back the same day to put you in the book.',
                ]],
                ['type' => 'visit', 'data' => [
                    'heading' => 'The studio *today*',
                    'intro' => 'Six chairs, by appointment. Call for a cancellation — they come up more often than you would think.',
                    'show_hours' => true,
                ]],
                ['type' => 'faq', 'data' => [
                    'heading' => 'Before you *book*',
                    'questions' => [
                        ['question' => 'What is the cancellation policy?', 'answer' => 'Twenty-four hours, and we mean it kindly — the chair is ninety minutes that somebody else wanted.'],
                        ['question' => 'Can I bring a picture?', 'answer' => 'Please do. It is by far the fastest way to get what is in your head onto your hands.'],
                        ['question' => 'How do you take payment?', 'answer' => 'Card, tap or cash, and tips in any of the three.'],
                    ],
                ]],
            ],
        ];
    }
}
