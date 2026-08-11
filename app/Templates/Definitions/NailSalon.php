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
 * Independent nail studio — the dark-room template.
 *
 * NightLounge, unmodified, and here the preset picked the template rather than
 * the other way round: its own `vibes()` are `['bar', 'nightlife', 'barber',
 * 'tattoo', 'salon', 'music']`, so *salon* is literally one of the industry
 * nouns this preset answers to. A category of "nail salon" would be matched to
 * NightLounge on the AI draft path anyway; curating the template onto anything
 * else would mean the hand-made version and the generated version disagreed
 * about the same trade.
 *
 * Near-black and gold, heavy Impact type on a slanted seam — the one preset in
 * the library that renders a dark site, and that is the entire art direction.
 * Nail photography is small, glossy and lit from one side: on white it reads as
 * a clinical close-up, on black it reads as jewellery. So the gallery sits
 * SECOND, directly under the hero, where a visitor deciding whether to book is
 * really deciding on the pictures.
 *
 * No token overrides, and no `inverted` tone anywhere — on a dark ramp `muted`
 * is the LIGHTER step, so raised panels go there, and the gold `accent` is
 * spent exactly once, on the booking banner.
 */
final readonly class NailSalon
{
    public static function definition(): TemplateDefinition
    {
        return new TemplateDefinition(
            preset: StylePreset::NightLounge,
            brandPrimary: '#C9A227',
            brandSecondary: '#12100E',
            brandAccent: '#E6D3A3',
            category: 'nail salon',
            chrome: self::chrome(),
            pages: [self::home(), self::services(), self::contact()],
            photoQueries: self::photoQueries(),
            demoProfile: self::demoProfile(),
            extraFields: self::extraFields(),
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
            new PhotoQuery('nail salon interior dark', category: PhotoCategory::Interior, count: 4),
            new PhotoQuery('manicure hands treatment', category: PhotoCategory::People, count: 4),
            new PhotoQuery('beauty salon gold detail', category: PhotoCategory::Interior, count: 3),
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
                'variant' => 'inverted',
                'nav_links' => [
                    ['label' => 'Home', 'url' => '/'],
                    ['label' => 'Menu', 'url' => '/services'],
                    ['label' => 'Visit', 'url' => '/contact'],
                ],
                'cta_label' => 'Book a chair',
                'cta_url' => 'tel:{phone}',
            ]],
            ['type' => 'footer', 'data' => [
                'variant' => 'minimal',
                'nav_links' => [
                    ['label' => 'Menu', 'url' => '/services'],
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
                ['type' => 'hero', 'variant' => 'full-bleed-overlay', 'data' => [
                    'eyebrow' => '{city}',
                    'heading' => '{tagline}',
                    'subheading' => 'Six chairs, ninety-minute appointments, and a lamp bright enough to catch every edge. No double-booking, no rushing the cuticle work.',
                    'cta_label' => 'See the menu',
                    'cta_url' => '/services',
                    'image_query' => 'nail salon interior dark',
                ]],
                ['type' => 'visit', 'data' => [
                    'heading' => 'The room today',
                    'intro' => 'Six chairs, by appointment. Call for a cancellation — they come up more often than you would think.',
                    'show_hours' => true,
                ]],
                ['type' => 'gallery', 'variant' => 'masonry', 'tone' => 'base', 'spacing' => 'tight', 'data' => [
                    'heading' => 'Recent sets',
                    'images' => [
                        ['alt' => 'Chrome over a smoked almond set'],
                        ['alt' => 'Micro-French in oxblood'],
                        ['alt' => 'Gold foil, painted freehand'],
                        ['alt' => 'A short square set, high gloss'],
                        ['alt' => 'Negative-space line work'],
                        ['alt' => 'A single accent nail in leaf'],
                    ],
                    'image_query' => 'nail art manicure close up',
                ]],
                ['type' => 'offerings', 'tone' => 'muted', 'data' => [
                    'heading' => 'The short menu',
                    'intro' => 'The three things we are asked for most. Everything else, including art pricing, is on the menu page.',
                    'items' => [
                        ['group' => 'Hands', 'name' => '{service_one}', 'price' => '{service_one_price}', 'description' => 'Structure first — the apex built where it belongs, so the set grows out flat instead of lifting in week three.'],
                        ['group' => 'Hands', 'name' => '{service_two}', 'price' => '{service_two_price}', 'description' => 'Painted freehand at the chair. Bring a reference or hand it over entirely; both go fine.'],
                        ['group' => 'Feet', 'name' => '{service_three}', 'price' => '{service_three_price}', 'description' => 'An hour off your feet. Dry-filed, finished and out the door without a soggy sandal.'],
                    ],
                ]],
                // Straight after the menu, where the visitor has just seen a
                // price and is deciding — not at the bottom of the page, which
                // most of them never reach. One field, because a phone number
                // is all a salon needs to call someone back.
                ['type' => 'signup', 'variant' => 'banner', 'tone' => 'accent', 'data' => [
                    'heading' => 'Want a cancellation?',
                    'offer' => 'Leave your number and we will text you when a chair comes free this week.',
                    'fields' => 'phone',
                    'button_label' => 'Text me',
                    'success_message' => 'Got it — we will text you the moment something opens up.',
                    'fine_print' => 'Only about cancellations. Reply STOP any time.',
                ]],
                ['type' => 'prose', 'variant' => 'side-heading', 'tone' => 'muted', 'data' => [
                    'heading' => 'Why the room is dark',
                    'paragraphs' => [
                        ['text' => 'The overheads are low because nobody relaxes under a supermarket ceiling. The light that matters is at the chair — colour-true, aimed at about ten square centimetres of work, and honest about what a shade will actually look like in daylight.'],
                        ['text' => 'Everything else is deliberate too. One client per technician, no drill on the natural nail, and a fresh tray out of the autoclave for every appointment.'],
                    ],
                ]],
                ['type' => 'testimonials', 'variant' => 'grid', 'tone' => 'muted', 'data' => [
                    'heading' => 'Said on the way out',
                    'testimonials' => [
                        ['quote' => 'Three weeks in and not one lift. I keep checking the edges, and they keep being fine.', 'author' => 'Priya N.', 'role' => 'In every month since 2023'],
                        ['quote' => 'I asked for something loud for a wedding and got the exact shade that was in my head, which has genuinely never happened to me before.', 'author' => 'Dominique R.', 'role' => 'Bride, June'],
                    ],
                ]],
                ['type' => 'cta', 'variant' => 'banner', 'tone' => 'accent', 'data' => [
                    'heading' => 'The book opens on Sundays',
                    'body' => 'Appointments go up a week at a time. Call and we will find you a chair — or a cancellation, which comes up more often than you would think.',
                    'cta_label' => 'Call {phone}',
                    'cta_url' => 'tel:{phone}',
                    'secondary_label' => 'See the menu',
                    'secondary_url' => '/services',
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
            'title' => 'Menu',
            'meta_description' => 'Manicures, pedicures and nail art at {business_name} in {city}, with prices and how long each appointment takes.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'centered-minimal', 'data' => [
                    'eyebrow' => 'Menu',
                    'heading' => 'What a chair gets you',
                    'subheading' => 'Every price below is the price of the appointment, not the opening bid. Art is quoted at the chair before anything is painted.',
                ]],
                ['type' => 'offerings', 'tone' => 'muted', 'data' => [
                    'heading' => 'Hands, feet, and everything painted',
                    'intro' => "Removal of our own work is included. Removal of somebody else's is fifteen dollars and no commentary.",
                    'items' => [
                        ['group' => 'Hands', 'name' => '{service_one}', 'price' => '{service_one_price}', 'description' => 'Ninety minutes. Cuticle work under magnification, a built apex, and a finish that files flat rather than domed.'],
                        ['group' => 'Hands', 'name' => '{service_two}', 'price' => 'from {service_two_price}', 'description' => 'Two hours with art on it. Line work, chrome, foil or hand-painted — priced by what is on the nail, agreed before we start.'],
                        ['group' => 'Hands', 'name' => 'Removal and reset', 'price' => 'Free on our own work', 'description' => 'Soaked off slowly, never pried. If you are taking a break from gel, we will send you home with the nail in decent shape.'],
                        ['group' => 'Feet', 'name' => '{service_three}', 'price' => '{service_three_price}', 'description' => 'No basin and no soaking — dry work holds longer and looks better the next morning. Callus work included.'],
                        ['group' => 'Art', 'name' => 'Freehand, foil and chrome', 'price' => 'Quoted at the chair', 'description' => 'Anything from a single accent nail to ten. Show us the photo and we will tell you honestly what will survive a fortnight.'],
                    ],
                ]],
                ['type' => 'steps', 'variant' => 'list', 'tone' => 'base', 'data' => [
                    'heading' => 'How an appointment runs',
                    'intro' => 'Ninety minutes most of the time. Two hours if there is art on it.',
                    'steps' => [
                        ['title' => 'Ten minutes talking', 'description' => 'Shape, length, colour, and what your week looks like. If what you want will not last on your nails, you hear it now rather than at the end.'],
                        ['title' => 'Prep, properly', 'description' => 'Cuticle work under magnification, no drill on the natural nail, and a dry, dehydrated surface before a single layer goes down.'],
                        ['title' => 'Build, then paint', 'description' => 'Structure first, colour second, art last — painted freehand while everything is still adjustable.'],
                        ['title' => 'Two weeks later', 'description' => 'A free file and check-in if anything catches. It rarely does, but the offer stands and people use it.'],
                    ],
                ]],
                ['type' => 'faq', 'tone' => 'muted', 'data' => [
                    'heading' => 'Asked at the desk',
                    'intro' => 'The questions that come up before a first appointment.',
                    'questions' => [
                        ['question' => 'Do you take walk-ins?', 'answer' => 'Rarely, and only when somebody cancels. Call {phone} and ask — Tuesday afternoons are the likeliest.'],
                        ['question' => "Can you fill another salon's set?", 'answer' => 'We will take it off and start clean instead. There is no way to know what is underneath, and we would rather own the whole thing.'],
                        ['question' => 'How long does a set actually last?', 'answer' => 'Three to four weeks before a fill, longer if you are gentle with them. Anything lifting inside two weeks is our problem to fix, free.'],
                        ['question' => 'Do you use a drill?', 'answer' => 'On product, yes. On your natural nail, never — that is where the thinning and the sensitivity come from.'],
                    ],
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
                ['type' => 'hero', 'variant' => 'centered-minimal', 'data' => [
                    'eyebrow' => '{city}',
                    'heading' => 'Book a chair',
                    'subheading' => 'The phone is answered between clients, so leave a message if it rings out. Everybody gets called back the same day.',
                ]],
                ['type' => 'contact', 'tone' => 'base', 'spacing' => 'airy', 'data' => [
                    'heading' => 'Where to find us',
                    'intro' => 'Street parking is free after six. If the door is locked we are mid-appointment — buzz once and give us a minute.',
                    'show_form' => true,
                    'success_message' => 'Thank you — we will call you back the same day to put you in the book.',
                ]],
                ['type' => 'gallery', 'variant' => 'filmstrip', 'tone' => 'base', 'spacing' => 'tight', 'data' => [
                    'heading' => 'The room',
                    'images' => [
                        ['alt' => 'The row of chairs at opening'],
                        ['alt' => 'A work lamp over a clean tray'],
                        ['alt' => 'The colour wall'],
                    ],
                    'image_query' => 'nail salon interior dark',
                ]],
                ['type' => 'faq', 'tone' => 'muted', 'data' => [
                    'heading' => 'Before you book',
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
