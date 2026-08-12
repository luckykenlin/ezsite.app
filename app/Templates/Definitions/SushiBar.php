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
 * An omakase sushi counter — the quietest restaurant in the library.
 *
 * {@see ChineseRestaurant} is a shopfront selling dinner tonight and
 * {@see FineDining} is a dining room selling an evening; a twelve-seat counter
 * sells something narrower than either — a chair in front of one person's
 * hands, booked days ahead and gone if you hesitate. It is reservation-led
 * like FineDining (the `reservation` block closes the home page and owns the
 * contact page, with a party cap small enough to protect the counter), but the
 * register is the opposite of NightLounge's velvet: restraint, whitespace,
 * nothing raised above speaking volume.
 *
 * Which is why it runs QuietLuxe untouched, no token swapped. The preset was
 * proven on {@see HairStudio} — warm stone, hairline serif, Reveal motion, the
 * `full-viewport-quiet` hero with no photograph in the first viewport — and
 * the same tokens read here as hinoki, paper and an ink brand mark: the "one
 * system, two rooms" pitch made across trades rather than within one. The
 * imageless opening earns its second outing for the same reason it earned its
 * first: stock sushi photography is what every takeaway on the street leads
 * with, and a claim set large in quiet type is what only this counter can say.
 */
final readonly class SushiBar
{
    public static function definition(): TemplateDefinition
    {
        return new TemplateDefinition(
            preset: StylePreset::QuietLuxe,
            brandPrimary: '#3F3A33',
            brandSecondary: '#191713',
            brandAccent: '#9A4A32',
            category: 'sushi restaurant',
            chrome: self::chrome(),
            pages: [self::home(), self::about(), self::contact()],
            photoQueries: self::photoQueries(),
            demoProfile: self::demoProfile(),
            extraFields: self::extraFields(),
        );
    }

    private static function demoProfile(): DemoProfile
    {
        return new DemoProfile(
            name: 'Kaede',
            tagline: 'Twelve seats at the counter, one omakase',
            description: 'A twelve-seat sushi counter serving a nightly omakase: fish bought that morning, rice seasoned to match it, and one chef working in front of you the whole way through.',
            city: 'Seattle',
            phone: '(206) 555-0142',
            email: 'reserve@kaede.example',
            addressLine1: '1516 Second Avenue',
            state: 'WA',
            postalCode: '98101',
            timezone: 'America/Los_Angeles',
            latitude: 47.6153,
            longitude: -122.3320,
            // Dinner only, closed Sunday and Monday — the week a one-chef
            // counter can actually keep, and Monday is the market's day off too.
            openingHours: [
                'monday' => [],
                'tuesday' => ['17:00-22:00'],
                'wednesday' => ['17:00-22:00'],
                'thursday' => ['17:00-22:00'],
                'friday' => ['17:00-22:00'],
                'saturday' => ['17:00-22:00'],
                'sunday' => [],
            ],
        );
    }

    /**
     * @return list<TemplateField>
     */
    private static function extraFields(): array
    {
        return [
            new TemplateField('omakase_name', 'The 12-course omakase'),
            new TemplateField('omakase_price', '$95'),
            new TemplateField('nigiri_one', 'Bluefin chū-toro'),
            new TemplateField('nigiri_one_price', '$12'),
            new TemplateField('roll_one', 'Salmon, yuzu & shiso'),
            new TemplateField('roll_one_price', '$14'),
        ];
    }

    /**
     * @return list<PhotoQuery>
     */
    private static function photoQueries(): array
    {
        return [
            new PhotoQuery('nigiri sushi close up minimal', category: PhotoCategory::FoodDrink, count: 4),
            new PhotoQuery('sushi counter restaurant interior wood', category: PhotoCategory::Interior, count: 4),
            new PhotoQuery('omakase course plating ceramic', category: PhotoCategory::FoodDrink, count: 3),
            new PhotoQuery('sushi chef hands shaping nigiri', PhotoOrientation::Portrait, PhotoCategory::People, 3),
        ];
    }

    /**
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    private static function chrome(): array
    {
        return [
            ['type' => 'header', 'data' => [
                'variant' => 'simple',
                'nav_links' => [
                    ['label' => 'Home', 'url' => '/'],
                    ['label' => 'The counter', 'url' => '/about'],
                    ['label' => 'Reserve', 'url' => '/contact'],
                ],
                'cta_label' => 'Reserve a seat',
                'cta_url' => '/contact',
            ]],
            ['type' => 'footer', 'data' => [
                'variant' => 'minimal',
                'nav_links' => [
                    ['label' => 'The counter', 'url' => '/about'],
                    ['label' => 'Reserve', 'url' => '/contact'],
                ],
                'note' => '{business_name} — {city}. Twelve seats, Tuesday to Saturday, from five.',
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
            'meta_description' => '{business_name} in {city} — {tagline}. Dinner Tuesday to Saturday, by reservation.',
            'blocks' => [
                // Imageless on purpose, like the hair studio's: a screenful of
                // warm stone and a short line does what no stock photograph of
                // nigiri can. The heading is authored short — a headline at
                // this size is a two-or-three word instrument — and the
                // tagline opens the sentence underneath it instead.
                ['type' => 'hero', 'variant' => 'full-viewport-quiet', 'data' => [
                    'eyebrow' => '{city}',
                    'heading' => 'Sit at the counter',
                    'subheading' => '{tagline}. Two seatings a night, Tuesday to Saturday, and the menu is whatever the morning market was good at.',
                    'cta_label' => 'Reserve a seat',
                    'cta_url' => '/contact',
                ]],
                ['type' => 'visit', 'data' => [
                    'heading' => 'Two seatings, from five',
                    'intro' => 'Every chair faces the cutting board. Reservations open thirty days out; the last seat of the week usually goes by Wednesday.',
                    'show_hours' => true,
                    'show_map' => true,
                    'note' => 'The door is the plain cedar one — no sign, just the maple leaf etched at eye height.',
                ]],
                ['type' => 'offerings', 'tone' => 'base', 'spacing' => 'airy', 'data' => [
                    'heading' => 'The card, kept short',
                    'intro' => 'Most of the counter orders the omakase and lets the morning decide. The nigiri and rolls below are for adding on, or for the bar seats.',
                    'items' => [
                        ['group' => 'Omakase', 'name' => '{omakase_name}', 'price' => '{omakase_price}', 'description' => 'Served in the order it was cut, one piece at a time, each one seasoned before it reaches you. About two hours, and no decisions to make after the first one.'],
                        ['group' => 'Omakase', 'name' => 'The 8-course, early seating', 'price' => '$70', 'description' => 'The shorter arc for the five o\'clock chairs — the same fish, four fewer courses, and out in time for the rest of your evening.'],
                        ['group' => 'Nigiri', 'name' => '{nigiri_one}', 'price' => '{nigiri_one_price}', 'description' => 'Cut to order from the middle of the loin. Eat it in the first ten seconds, while the rice is still warm.'],
                        ['group' => 'Nigiri', 'name' => 'Hokkaido uni, brushed nikiri', 'price' => '$16', 'description' => 'When the box that morning was worth opening. When it was not, this line is quietly missing.'],
                        ['group' => 'Nigiri', 'name' => 'Saba, cured in-house', 'price' => '$8', 'description' => 'Salted and vinegared the day before — the piece regulars use to judge a new counter, and welcome to.'],
                        ['group' => 'Rolls & sides', 'name' => '{roll_one}', 'price' => '{roll_one_price}', 'description' => 'Six pieces, cut clean, the shiso doing the work a sauce would do somewhere louder.'],
                        ['group' => 'Rolls & sides', 'name' => 'Clam miso, to finish', 'price' => '$6', 'description' => "Made from the day's shells and bones. The bowl most people did not order and are glad arrived."],
                    ],
                ]],
                ['type' => 'prose', 'variant' => 'side-heading', 'tone' => 'muted', 'data' => [
                    'heading' => 'The rice is half the work',
                    'paragraphs' => [
                        ['text' => 'The fish gets the attention, but the rice gets the hours: cooked in small batches through the night, seasoned with an aged red vinegar, and held at body temperature so it gives way exactly when the fish does.'],
                        ['text' => 'What sits on top of it changes weekly. The tuna is bought whole and broken down here; the rest comes off the morning flight or the local boats, and the card is written after the boxes are opened, never before.'],
                        ['text' => 'That is also why nothing on this page promises a specific fish on a specific night. The season decides, and the season is usually right.'],
                    ],
                ]],
                ['type' => 'gallery', 'variant' => 'filmstrip', 'tone' => 'base', 'data' => [
                    'heading' => 'Along the counter',
                    'images' => [
                        ['alt' => 'The counter set for the first seating'],
                        ['alt' => 'A piece of nigiri, just brushed and served'],
                        ['alt' => 'Hands shaping rice at the cutting board'],
                        ['alt' => 'The cedar door, lantern lit at dusk'],
                    ],
                    'image_query' => 'sushi counter restaurant interior wood',
                ]],
                ['type' => 'testimonials', 'variant' => 'spotlight', 'tone' => 'muted', 'data' => [
                    'heading' => 'From the twelve chairs',
                    'testimonials' => [
                        ['quote' => 'Nobody explained anything at length and nothing needed explaining. A piece arrived, you ate it, and the next one was already being made. Two hours went somewhere.', 'author' => 'Mireille T.', 'role' => 'Second seating, most months'],
                        ['quote' => 'I stopped ordering the same three things at other places after sitting here once. You hand the evening over and it comes back better than you would have planned it.', 'author' => 'James K.', 'role' => 'Counter regular'],
                    ],
                ]],
                ['type' => 'reservation', 'tone' => 'base', 'spacing' => 'airy', 'data' => [
                    'heading' => 'Reserve a seat',
                    'intro' => 'The counter seats twelve, in two seatings. Tell us the evening and how many chairs, and we confirm by phone the same day.',
                    'max_party_size' => 4,
                    'button_label' => 'Request seats',
                    'success_message' => 'Request received — we will call today to confirm your seats.',
                    'fine_print' => 'Five or more? Call {phone} — beyond four, a party changes the counter for everyone else, so we will plan it with you.',
                ]],
                ['type' => 'cta', 'variant' => 'banner', 'tone' => 'accent', 'data' => [
                    'heading' => 'The early seating is the quiet one',
                    'body' => "Five o'clock has the first cut of everything and the counter to itself for half an hour.",
                    'cta_label' => 'How the counter runs',
                    'cta_url' => '/about',
                    'secondary_label' => 'Reserve a seat',
                    'secondary_url' => '/contact',
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
            'title' => 'The counter',
            'meta_description' => 'The market, the rice and the twelve seats of {business_name} in {city}.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'centered-minimal', 'data' => [
                    'eyebrow' => 'The counter',
                    'heading' => 'Twelve seats, one pair of hands',
                    'subheading' => "Everything served here is cut, shaped and seasoned within arm's reach of the person eating it.",
                ]],
                ['type' => 'prose', 'variant' => 'stacked', 'data' => [
                    'heading' => 'What the discipline buys',
                    'paragraphs' => [
                        ['text' => 'Twelve seats is the number one chef can serve without a piece ever waiting. Each course is finished when the chair in front of it is ready and not a moment before — which is the whole argument for a counter over a table.'],
                        ['text' => 'The rice took longer to get right than anything else in the room. It is a blend of two crops, washed until the water runs clear, cooked in small pots through service, and seasoned differently for the lean fish than for the rich.'],
                        ['text' => 'The fish is bought whole where the size allows and broken down in the back each morning. Some of it is served within hours; some is salted, cured or aged for days first, because a good piece of mackerel is made, not found.'],
                    ],
                ]],
                ['type' => 'steps', 'variant' => 'timeline', 'tone' => 'muted', 'data' => [
                    'heading' => 'A day, in order',
                    'intro' => 'The same loop five days a week, and the reason the card is never written before ten in the morning.',
                    'steps' => [
                        ['title' => 'Market', 'description' => 'At the stalls by six. What is bought is whatever was best that morning, not whatever the menu promised.'],
                        ['title' => 'Rice', 'description' => 'Washed, rested, cooked in small batches and seasoned while warm. The pot is remade through the night rather than held.'],
                        ['title' => 'Cut', 'description' => "The morning's fish broken down, cured or set aside to age. The card is written only after the last box is open."],
                        ['title' => 'Serve', 'description' => 'Two seatings, twelve chairs each, one piece at a time. Nothing leaves the board until the person it is for is ready.'],
                    ],
                ]],
                ['type' => 'gallery', 'variant' => 'grid', 'data' => [
                    'heading' => 'Before the first seating',
                    'images' => [
                        ['alt' => "The morning's fish laid out for breaking down"],
                        ['alt' => 'Rice being turned and seasoned in the hangiri'],
                        ['alt' => 'The knife, stoned and wiped before service'],
                    ],
                    'image_query' => 'sushi chef hands shaping nigiri',
                ]],
                ['type' => 'cta', 'variant' => 'banner', 'data' => [
                    'heading' => 'Take one of the twelve',
                    'body' => 'The counter makes its case a course at a time. Choose an evening and let the market do the rest.',
                    'cta_label' => 'Reserve a seat',
                    'cta_url' => '/contact',
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
            'title' => 'Reserve',
            'meta_description' => 'Reserve a seat at {business_name}, {city} — hours, the address and the request form.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'centered-minimal', 'data' => [
                    'eyebrow' => '{city}',
                    'heading' => 'Reserve a seat',
                    'subheading' => 'Twelve chairs, two seatings, thirty days of the book open at a time. Weekends go first.',
                ]],
                ['type' => 'reservation', 'tone' => 'base', 'spacing' => 'airy', 'data' => [
                    'heading' => 'Request your seats',
                    'intro' => 'Pick an evening and a seating — five o\'clock or eight. Every request is confirmed by phone the same day.',
                    'max_party_size' => 4,
                    'button_label' => 'Request seats',
                    'success_message' => 'Request received — we will call today to confirm your seats.',
                    'fine_print' => 'Five or more? Call {phone} — beyond four, a party changes the counter for everyone else, so we will plan it with you.',
                ]],
                ['type' => 'visit', 'tone' => 'muted', 'data' => [
                    'heading' => 'Finding the door',
                    'intro' => 'The plain cedar door on Second, maple leaf at eye height. If you arrive before five, the lantern is not on yet but we are here.',
                    'show_hours' => true,
                    'show_map' => true,
                ]],
                ['type' => 'faq', 'tone' => 'base', 'data' => [
                    'heading' => 'Before you book',
                    'questions' => [
                        ['question' => 'Can the omakase work around allergies or a vegetarian?', 'answer' => 'Allergies, yes, with a note on the booking — the card is written each morning, so the kitchen adjusts before you sit down. A fully vegetarian omakase needs two days\' notice and is worth those two days.'],
                        ['question' => 'Can we bring children?', 'answer' => 'From ten, if they will sit for the two hours — the counter has no room to be halfway in a meal. Younger, or restless, the early seating on a Tuesday is the kindest chair in the house.'],
                        ['question' => 'What is the cancellation policy?', 'answer' => "Forty-eight hours' notice, no charge. Inside that we ask for half the omakase per seat, because a chair at a twelve-seat counter cannot be resold the same afternoon."],
                        ['question' => 'Do you take walk-ins?', 'answer' => 'The two bar seats by the door are kept for whoever arrives first, for nigiri and rolls rather than the full omakase. Everything else is by reservation.'],
                    ],
                ]],
            ],
        ];
    }
}
