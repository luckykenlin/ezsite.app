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
 * Fine dining — the reservation-first restaurant.
 *
 * {@see ChineseRestaurant} and {@see PizzaShop} are shopfronts: the page's job
 * is to get someone through the door tonight, so the phone leads. A dining
 * room that seats forty and turns tables twice sells something else — an
 * evening, planned days ahead — which is why this is the first template built
 * around the `reservation` block: the booking form closes the home page and
 * owns the contact page, and every CTA points at it rather than at `tel:`.
 *
 * NightLounge untouched: near-black with gold, Bodoni Moda over Karla, impact
 * type. The preset's own description says dinner-first, and no token needs
 * swapping to make a white-tablecloth room out of it — the nail salon
 * (NightLounge's other tenant) proves the same tokens read as lacquer and
 * gold there, which is the "one system, two rooms" pitch from the other side.
 */
final readonly class FineDining
{
    public static function definition(): TemplateDefinition
    {
        return new TemplateDefinition(
            preset: StylePreset::NightLounge,
            brandPrimary: '#B08D3E',
            brandSecondary: '#16130E',
            brandAccent: '#6E2C2A',
            category: 'fine dining restaurant',
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
            name: 'Aurelia',
            tagline: 'Eight tables, one sitting, no hurry',
            description: 'A small dining room doing a short, seasonal menu properly: dry-aged beef, old-vine wine, and a kitchen you can hear but not see.',
            city: 'Chicago',
            phone: '(312) 555-0184',
            email: 'reservations@aurelia.example',
            addressLine1: '11 W Superior Street',
            state: 'IL',
            postalCode: '60654',
            timezone: 'America/Chicago',
            latitude: 41.8955,
            longitude: -87.6285,
            // Dinner only, closed Sunday and Monday — the trading week of a
            // room this size, and what makes the "Open now" line truthful.
            openingHours: [
                'monday' => [],
                'tuesday' => ['17:00-22:00'],
                'wednesday' => ['17:00-22:00'],
                'thursday' => ['17:00-22:00'],
                'friday' => ['17:00-23:00'],
                'saturday' => ['17:00-23:00'],
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
            new TemplateField('starter_dish', 'Seared scallops, brown butter & caper'),
            new TemplateField('starter_dish_price', '$24'),
            new TemplateField('main_dish', '45-day dry-aged ribeye for two'),
            new TemplateField('main_dish_price', '$120'),
            new TemplateField('dessert_dish', 'Burnt honey tart, crème fraîche'),
            new TemplateField('dessert_dish_price', '$16'),
        ];
    }

    /**
     * @return list<PhotoQuery>
     */
    private static function photoQueries(): array
    {
        return [
            new PhotoQuery('fine dining plated dish dark moody', category: PhotoCategory::FoodDrink, count: 4),
            new PhotoQuery('candlelit restaurant dining room evening', category: PhotoCategory::Interior, count: 4),
            new PhotoQuery('sommelier pouring red wine', category: PhotoCategory::FoodDrink, count: 3),
            new PhotoQuery('chef plating with tweezers kitchen', PhotoOrientation::Portrait, PhotoCategory::People, 3),
        ];
    }

    /**
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    private static function chrome(): array
    {
        return [
            ['type' => 'header', 'data' => [
                'variant' => 'centered',
                'nav_links' => [
                    ['label' => 'Home', 'url' => '/'],
                    ['label' => 'The room', 'url' => '/about'],
                    ['label' => 'Reservations', 'url' => '/contact'],
                ],
                'cta_label' => 'Book a table',
                'cta_url' => '/contact',
            ]],
            ['type' => 'footer', 'data' => [
                'variant' => 'minimal',
                'nav_links' => [
                    ['label' => 'The room', 'url' => '/about'],
                    ['label' => 'Reservations', 'url' => '/contact'],
                ],
                'note' => '{business_name} — {city}. Dinner from five, Tuesday to Saturday.',
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
            'meta_description' => '{business_name} in {city} — {tagline}. Dinner Tuesday to Saturday, reservations recommended.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'full-bleed-overlay', 'data' => [
                    'eyebrow' => '{city}',
                    'heading' => '{tagline}',
                    'subheading' => 'A short menu that changes with the market, a cellar that does not, and a room quiet enough to hear the person across the table.',
                    'cta_label' => 'Book a table',
                    'cta_url' => '/#lead-reservation-1',
                    'image_query' => 'candlelit restaurant dining room evening',
                ]],
                ['type' => 'visit', 'data' => [
                    'heading' => 'Evenings only',
                    'intro' => 'Dinner from five. The bar seats walk-ins; the dining room is by reservation.',
                    'show_hours' => true,
                    'show_map' => true,
                    'note' => 'Valet on Superior after six; the door is the unmarked one under the lamp.',
                ]],
                ['type' => 'offerings', 'tone' => 'muted', 'spacing' => 'airy', 'data' => [
                    'heading' => 'Tonight, in three acts',
                    'intro' => 'The card is one page and changes with the market. These stay on it by popular demand.',
                    'items' => [
                        ['group' => 'To begin', 'name' => '{starter_dish}', 'price' => '{starter_dish_price}', 'description' => 'The plate regulars refuse to let us retire. Small, precise, and gone in four bites you will think about later.'],
                        ['group' => 'To begin', 'name' => 'Chicory, anchovy & aged parmesan', 'price' => '$18', 'description' => 'Bitter leaves dressed table-side. The quiet start people order twice.'],
                        ['group' => 'The main event', 'name' => '{main_dish}', 'price' => '{main_dish_price}', 'description' => 'Carved at the table, rested properly, and worth planning an evening around. For two, and genuinely for two.'],
                        ['group' => 'The main event', 'name' => 'Monkfish, mussel butter & sea herbs', 'price' => '$52', 'description' => 'The kitchen’s answer for the half of the table not having the beef.'],
                        ['group' => 'To finish', 'name' => '{dessert_dish}', 'price' => '{dessert_dish_price}', 'description' => 'Made in the afternoon in small numbers. When it runs out, the cheese trolley consoles.'],
                        ['group' => 'To finish', 'name' => 'The cheese trolley', 'price' => '$22', 'description' => 'Five cheeses in good condition, wheeled over with opinions attached.'],
                    ],
                ]],
                ['type' => 'prose', 'variant' => 'side-heading', 'tone' => 'base', 'data' => [
                    'heading' => 'The idea of the place',
                    'paragraphs' => [
                        ['text' => 'Eight tables is a decision, not a limitation. It is the number at which the kitchen can cook every plate to order and the room can hold a conversation at ordinary volume.'],
                        ['text' => 'The menu is short because the market writes it. What was good at dawn is what is on the card at seven, and the wine list leans old-world because that is what this food wants beside it.'],
                    ],
                ]],
                ['type' => 'gallery', 'variant' => 'filmstrip', 'tone' => 'muted', 'data' => [
                    'heading' => 'The room, after five',
                    'images' => [
                        ['alt' => 'The dining room lit for the first seating'],
                        ['alt' => 'A plate being finished at the pass'],
                        ['alt' => 'Wine decanted at the sideboard'],
                        ['alt' => 'The corner table, set for two'],
                    ],
                    'image_query' => 'fine dining plated dish dark moody',
                ]],
                ['type' => 'testimonials', 'variant' => 'spotlight', 'tone' => 'base', 'data' => [
                    'heading' => 'Word of mouth',
                    'testimonials' => [
                        ['quote' => 'We came for an anniversary and the room did the thing good rooms do — the evening slowed down. Nobody hovered, nothing was rushed, and the ribeye was the best either of us has had.', 'author' => 'Daniel & Rose M.', 'role' => 'Anniversary table'],
                        ['quote' => 'Ask for the counter seats if you like watching a kitchen that never raises its voice.', 'author' => 'Priya V.', 'role' => 'Regular, Thursdays'],
                    ],
                ]],
                ['type' => 'reservation', 'tone' => 'muted', 'spacing' => 'airy', 'data' => [
                    'heading' => 'Book a table',
                    'intro' => 'Tell us when and how many. We confirm every request the same day, by phone.',
                    'max_party_size' => 6,
                    'button_label' => 'Request a table',
                    'success_message' => 'Request received — we will ring you today to confirm.',
                    'fine_print' => 'Seven or more? Call {phone} and we will open the private room.',
                ]],
                ['type' => 'cta', 'variant' => 'banner', 'tone' => 'accent', 'data' => [
                    'heading' => 'The first seating is the quiet one',
                    'body' => 'Five-thirty has the last of the daylight and the whole cellar to choose from.',
                    'cta_label' => 'Reserve for tonight',
                    'cta_url' => '/contact',
                    'secondary_label' => 'See the room',
                    'secondary_url' => '/about',
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
            'title' => 'The room',
            'meta_description' => 'The kitchen, the cellar and the eight tables of {business_name} in {city}.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'centered-minimal', 'data' => [
                    'eyebrow' => 'Since the shutters came off',
                    'heading' => 'A room built around dinner',
                    'subheading' => 'One kitchen, one sitting a night at each table, and a menu written the same morning it is cooked.',
                ]],
                ['type' => 'prose', 'variant' => 'stacked', 'data' => [
                    'heading' => 'How it runs',
                    'paragraphs' => [
                        ['text' => 'The kitchen buys in the morning and writes the card at three. If the turbot is not right, there is no turbot tonight — the menu bends so the standard does not.'],
                        ['text' => 'Service is built to disappear. Water arrives before it is asked for, the next course waits for the conversation to pause, and the bill comes when you look for it and not before.'],
                        ['text' => 'The cellar is the long game: bottles bought young and kept until they are ready, which is why the list has depth in places a room this size has no right to.'],
                    ],
                ]],
                ['type' => 'team', 'tone' => 'muted', 'data' => [
                    'heading' => 'Who is cooking',
                    'members' => [
                        ['name' => 'Elena Marchetti', 'role' => 'Chef & owner', 'bio' => 'Fifteen years in other people’s kitchens, eight tables of her own. Writes the menu daily and still works the pass every service.'],
                        ['name' => 'Jonah Wells', 'role' => 'Head of wine', 'bio' => 'Keeps the cellar and the room’s pace. Will find you something better than the bottle you came in wanting, one shelf down in price.'],
                    ],
                ]],
                ['type' => 'stats', 'tone' => 'base', 'data' => [
                    'heading' => 'The room in numbers',
                    'stats' => [
                        ['value' => '8', 'label' => 'Tables in the dining room'],
                        ['value' => '1', 'label' => 'Page on the menu, rewritten daily'],
                        ['value' => '400+', 'label' => 'Bins in the cellar'],
                    ],
                ]],
                ['type' => 'gallery', 'variant' => 'grid', 'data' => [
                    'heading' => 'Before the first cover',
                    'images' => [
                        ['alt' => 'Linen going onto the corner table'],
                        ['alt' => 'The day’s menu being proofed'],
                        ['alt' => 'Glasses polished against the window light'],
                    ],
                    'image_query' => 'sommelier pouring red wine',
                ]],
                ['type' => 'cta', 'variant' => 'banner', 'data' => [
                    'heading' => 'See it at seven',
                    'body' => 'The room makes its own case an hour into service. Come and sit in it.',
                    'cta_label' => 'Book a table',
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
            'title' => 'Reservations',
            'meta_description' => 'Book a table at {business_name}, {city} — hours, address and the reservation form.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'centered-minimal', 'data' => [
                    'eyebrow' => '{city}',
                    'heading' => 'Reservations',
                    'subheading' => 'The dining room seats by reservation; the bar keeps four stools for whoever arrives first.',
                ]],
                ['type' => 'reservation', 'tone' => 'base', 'spacing' => 'airy', 'data' => [
                    'heading' => 'Request a table',
                    'intro' => 'Choose an evening and a time from five. We confirm every request by phone the same day.',
                    'max_party_size' => 6,
                    'button_label' => 'Request a table',
                    'success_message' => 'Request received — we will ring you today to confirm.',
                    'fine_print' => 'Seven or more? Call {phone} and we will open the private room.',
                ]],
                ['type' => 'visit', 'tone' => 'muted', 'data' => [
                    'heading' => 'Finding the door',
                    'intro' => 'The unmarked door under the lamp on Superior. Ring the bell if it is before five.',
                    'show_hours' => true,
                    'show_map' => true,
                ]],
                ['type' => 'faq', 'tone' => 'base', 'data' => [
                    'heading' => 'Before you book',
                    'questions' => [
                        ['question' => 'Is there a dress code?', 'answer' => 'No. People tend to dress for the evening because the room invites it, but nobody will be turned away over a collar.'],
                        ['question' => 'Can the kitchen handle allergies?', 'answer' => 'Yes, with notice. Put it in the booking note and the menu will be adjusted before you sit down, not negotiated at the table.'],
                        ['question' => 'How long do you hold a table?', 'answer' => 'Twenty minutes, and a phone call buys you more. After that the bar will look after you until the next slot opens.'],
                        ['question' => 'Do you do large parties?', 'answer' => 'The private room takes up to fourteen. Call {phone} rather than booking online and we will plan the menu with you.'],
                    ],
                ]],
            ],
        ];
    }
}
