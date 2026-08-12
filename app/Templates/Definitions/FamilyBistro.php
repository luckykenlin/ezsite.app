<?php

declare(strict_types=1);

namespace App\Templates\Definitions;

use App\Design\ColorPalette;
use App\Design\FontPair;
use App\Design\StylePreset;
use App\Enums\PhotoCategory;
use App\Templates\DemoProfile;
use App\Templates\PhotoQuery;
use App\Templates\TemplateDefinition;
use App\Templates\TemplateField;

/**
 * Neighbourhood family bistro — CalmCoastal's preset variation.
 *
 * Structurally it sits between {@see PizzaShop} (a shopfront with priced
 * offerings) and {@see FineDining} (the reservation-first dining room). A
 * room that gets booked for birthdays and Sunday lunch plans ahead like fine
 * dining but sells generosity, not an evening — so the `reservation` block
 * closes the home page and owns the contact page, at family scale: ten
 * bookable online, the long table by phone, high chairs in the booking note.
 *
 * CalmCoastal ({@see MassageSpa}'s preset), with `palette: WarmSand` and
 * `fontPair: Soft`. Everything that gives CalmCoastal its manner is untouched
 * — soft corners, spacious vertical rhythm, refined type and no dark band
 * anywhere to shout with — because a place people trust with a grandmother's
 * eightieth should feel unhurried, and the loud registers of the burger and
 * pizza templates would read as a queue. The swap only changes temperature:
 * seaside blue reads as water and towels, while warm sand under a soft
 * rounded pair reads as a dining room with room for a high chair. Same airy
 * calm, moved indoors, and the preset marker survives the override so both
 * tenants keep CalmCoastal's per-block opinions.
 */
final readonly class FamilyBistro
{
    public static function definition(): TemplateDefinition
    {
        return new TemplateDefinition(
            preset: StylePreset::CalmCoastal,
            brandPrimary: '#A65D3F',
            brandSecondary: '#3A2E26',
            brandAccent: '#D9A45B',
            category: 'family restaurant',
            chrome: self::chrome(),
            pages: [self::home(), self::about(), self::contact()],
            photoQueries: self::photoQueries(),
            demoProfile: self::demoProfile(),
            extraFields: self::extraFields(),
            palette: ColorPalette::WarmSand,
            fontPair: FontPair::Soft,
        );
    }

    private static function demoProfile(): DemoProfile
    {
        return new DemoProfile(
            name: 'The Linden',
            tagline: 'The table everyone fits around',
            description: 'A neighbourhood bistro with a long oak table by the window, a kids\' menu cooked in the same pots as everything else, and a ragù that goes on the stove on Friday.',
            city: 'Columbus',
            phone: '(614) 555-0163',
            email: 'hello@thelinden.example',
            addressLine1: '748 Mohawk Street',
            state: 'OH',
            postalCode: '43206',
            timezone: 'America/New_York',
            latitude: 39.9612,
            longitude: -82.9988,
            // Lunch through dinner all week — Sunday opens earlier because
            // Sunday lunch is the sitting this room is known for.
            openingHours: [
                'monday' => ['11:30-21:00'],
                'tuesday' => ['11:30-21:00'],
                'wednesday' => ['11:30-21:00'],
                'thursday' => ['11:30-21:00'],
                'friday' => ['11:30-22:00'],
                'saturday' => ['11:30-22:00'],
                'sunday' => ['11:00-20:00'],
            ],
        );
    }

    /**
     * @return list<TemplateField>
     */
    private static function extraFields(): array
    {
        return [
            new TemplateField('main_one', 'Half roast chicken, pan gravy'),
            new TemplateField('main_one_price', '$21'),
            new TemplateField('main_two', 'Rigatoni, Sunday ragù'),
            new TemplateField('main_two_price', '$18'),
            new TemplateField('kids_meal', 'The little Linden plate'),
            new TemplateField('kids_meal_price', '$9'),
        ];
    }

    /**
     * @return list<PhotoQuery>
     */
    private static function photoQueries(): array
    {
        return [
            new PhotoQuery('family dinner table sharing plates', category: PhotoCategory::FoodDrink, count: 4),
            new PhotoQuery('roast chicken dinner plate', category: PhotoCategory::FoodDrink, count: 3),
            new PhotoQuery('warm cozy bistro interior wooden tables', category: PhotoCategory::Interior, count: 4),
            new PhotoQuery('family eating at restaurant together', category: PhotoCategory::People, count: 3),
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
                    ['label' => 'Our story', 'url' => '/about'],
                    ['label' => 'Visit', 'url' => '/contact'],
                ],
                'cta_label' => 'Book a table',
                'cta_url' => '/contact',
            ]],
            ['type' => 'footer', 'data' => [
                'variant' => 'columns',
                'nav_links' => [
                    ['label' => 'Our story', 'url' => '/about'],
                    ['label' => 'Visit', 'url' => '/contact'],
                ],
                'note' => '{business_name} — {city}. Sunday lunch is the busy one — book ahead.',
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
            'meta_description' => '{business_name} in {city} — {tagline}. Lunch and dinner seven days, kids welcome, book for Sunday.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'left-text-right-image', 'data' => [
                    'eyebrow' => '{city}',
                    'heading' => '{tagline}',
                    'subheading' => 'Roast chicken carved for the middle of the table, a ragù that simmers all weekend, and enough room between the chairs for a stroller.',
                    'cta_label' => 'Book a table',
                    'cta_url' => '/contact',
                    'image_query' => 'family dinner table sharing plates',
                ]],
                ['type' => 'visit', 'data' => [
                    'heading' => 'Open every day',
                    'intro' => 'Lunch through dinner, seven days. Weeknights you can usually walk in — Sunday is another story.',
                    'show_hours' => true,
                    'show_map' => true,
                    'note' => 'Free lot behind the building, and high chairs stacked by the door — ask for one on the way in.',
                ]],
                ['type' => 'offerings', 'tone' => 'base', 'spacing' => 'airy', 'data' => [
                    'heading' => 'What the kitchen does best',
                    'intro' => 'One page, mostly unchanged for years, because you would notice. Portions are set by what a family actually finishes.',
                    'items' => [
                        ['group' => 'Mains', 'name' => '{main_one}', 'price' => '{main_one_price}', 'description' => 'Brined the night before, roasted to order, and big enough that someone small can eat off the edge of it.'],
                        ['group' => 'Mains', 'name' => '{main_two}', 'price' => '{main_two_price}', 'description' => 'The pot goes on Friday afternoon and comes off when it is ready. It is what the whole room smells like on a Sunday.'],
                        ['group' => 'Mains', 'name' => 'Skillet meatloaf, mashed potato & gravy', 'price' => '$17', 'description' => 'Nobody orders it on a first visit and everybody orders it by the third.'],
                        ['group' => 'For the kids', 'name' => '{kids_meal}', 'price' => '{kids_meal_price}', 'description' => 'A smaller plate from the same kitchen — real chicken, real vegetables, pasta from the same pot as yours. Milk or juice included.'],
                        ['group' => 'Puddings', 'name' => 'Warm apple crumble, vanilla ice cream', 'price' => '$8', 'description' => 'Baked in a tray the size of the oven and usually gone by nine.'],
                        ['group' => 'Puddings', 'name' => 'Chocolate pudding, soft cream', 'price' => '$7', 'description' => 'The one the kids order. The spoons come back clean.'],
                    ],
                ]],
                ['type' => 'prose', 'variant' => 'side-heading', 'tone' => 'muted', 'data' => [
                    'heading' => 'The ragù starts on Friday',
                    'paragraphs' => [
                        ['text' => 'It goes on the stove Friday afternoon and barely moves for two days — beef, pork, a little milk near the end, stirred whenever someone walks past. By Sunday lunch it has stopped being ingredients and become the reason people book the week before.'],
                        ['text' => 'The rest of the cooking is the kind you would do at home if you had the whole afternoon: stock from the chicken bones, gravy from the pan, bread warmed before it reaches the table. The difference is that here someone else sets it down, and nobody has to wash up.'],
                    ],
                ]],
                ['type' => 'gallery', 'variant' => 'grid', 'tone' => 'base', 'data' => [
                    'heading' => 'Around the room',
                    'images' => [
                        ['alt' => 'The long table set for a birthday lunch'],
                        ['alt' => 'Sunday ragù coming off the stove'],
                        ['alt' => 'The window booths filling up at noon'],
                        ['alt' => 'High chairs stacked by the front door'],
                    ],
                    'image_query' => 'warm cozy bistro interior wooden tables',
                ]],
                ['type' => 'testimonials', 'variant' => 'grid', 'tone' => 'muted', 'data' => [
                    'heading' => 'From the tables',
                    'testimonials' => [
                        ['quote' => 'We booked the long table for my mother\'s eightieth — eleven of us, three under six. They sat her at the head, the kids\' plates came out first, and nobody hurried us over coffee.', 'author' => 'Carol D.', 'role' => 'Booked the long table'],
                        ['quote' => 'Tuesday nights are ours. The kids ask for the same booth, the same pasta and the same waiter, and somehow all three are usually available.', 'author' => 'Marcus & Dana O.', 'role' => 'Every Tuesday'],
                    ],
                ]],
                ['type' => 'reservation', 'tone' => 'muted', 'spacing' => 'airy', 'data' => [
                    'heading' => 'Book the table',
                    'intro' => 'Birthdays and Sunday lunch go first. Tell us when, how many, and how many of those need a high chair.',
                    'max_party_size' => 10,
                    'button_label' => 'Request a table',
                    'success_message' => 'Request received — we will call to confirm before the day.',
                    'fine_print' => 'More than ten? Call {phone} — the long table seats sixteen.',
                ]],
                ['type' => 'cta', 'variant' => 'banner', 'tone' => 'accent', 'data' => [
                    'heading' => 'Sunday lunch books out first',
                    'body' => "The rest of the week you can usually walk in. For a birthday or a Sunday, give us a day's notice.",
                    'cta_label' => 'Book a table',
                    'cta_url' => '/contact',
                    'secondary_label' => 'Our story',
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
            'title' => 'Our story',
            'meta_description' => 'The kitchen, the long table and the people behind {business_name} in {city}.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'centered-minimal', 'data' => [
                    'eyebrow' => 'The story',
                    'heading' => 'A dining room that expects children',
                    'subheading' => 'One kitchen, one long table, and a menu that feeds a grandmother and a four-year-old off the same page.',
                ]],
                ['type' => 'prose', 'variant' => 'stacked', 'data' => [
                    'heading' => 'How it came to be',
                    'paragraphs' => [
                        ['text' => 'We took the lease for the window and the size of the kitchen. The long table was already here — sixteen feet of oak the previous owner could not move — so we built the room around it, and then the restaurant around the room.'],
                        ['text' => 'The kitchen has one rule: the plate errs on the side of too much. Portions are set by what a family actually finishes, not by what photographs well, and a second basket of bread never appears on the bill.'],
                        ['text' => 'Most of the front of house has been here for years. They know which regulars want the corner booth, which child will only eat the pasta plain, and which grandfather is ordering the meatloaf no matter what the specials board says.'],
                    ],
                ]],
                ['type' => 'team', 'tone' => 'muted', 'data' => [
                    'heading' => 'Who feeds you',
                    'members' => [
                        ['name' => 'Ruth Ellery', 'role' => 'Chef & owner', 'bio' => 'Cooked in bigger rooms for a decade and left to make food people order twice. Writes the specials, tastes the ragù, and still plates the kids\' meals herself on Sundays.'],
                        ['name' => 'Sam Okafor', 'role' => 'Front of house', 'bio' => 'Runs the book, the birthdays and the long table. If you called ahead about a cake, Sam is the one who hid it in the fridge.'],
                    ],
                ]],
                ['type' => 'gallery', 'variant' => 'grid', 'data' => [
                    'heading' => 'Before the doors open',
                    'images' => [
                        ['alt' => 'The long table laid for a Sunday sitting'],
                        ['alt' => 'Roast chickens resting on the pass'],
                        ['alt' => 'The kitchen door propped open at lunch'],
                    ],
                    'image_query' => 'roast chicken dinner plate',
                ]],
                ['type' => 'cta', 'variant' => 'banner', 'data' => [
                    'heading' => 'Come hungry, bring everyone',
                    'body' => 'Weeknights you can walk in. For Sunday lunch or a birthday, book — the long table goes first.',
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
            'title' => 'Visit',
            'meta_description' => 'Book a table at {business_name}, {city} — hours, address, parking and the booking form.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'centered-minimal', 'data' => [
                    'eyebrow' => '{city}',
                    'heading' => 'Book a table',
                    'subheading' => 'Weeknights usually have room for walk-ins. Sundays and birthdays are the ones to book.',
                ]],
                ['type' => 'reservation', 'tone' => 'base', 'spacing' => 'airy', 'data' => [
                    'heading' => 'Request a table',
                    'intro' => 'Pick a day, a time and how many of you there are. High chairs count — mention them in the note and one will be waiting at the table.',
                    'max_party_size' => 10,
                    'button_label' => 'Request a table',
                    'success_message' => 'Request received — we will call to confirm before the day.',
                    'fine_print' => 'More than ten? Call {phone} — the long table seats sixteen.',
                ]],
                ['type' => 'visit', 'tone' => 'muted', 'data' => [
                    'heading' => 'Finding us',
                    'intro' => 'On Mohawk Street, the corner with the green awning. The lot behind the building is free, and the sidewalk is wide enough for a stroller.',
                    'show_hours' => true,
                    'show_map' => true,
                ]],
                ['type' => 'faq', 'tone' => 'base', 'data' => [
                    'heading' => 'Before you book',
                    'questions' => [
                        ['question' => "Is there a proper kids' menu?", 'answer' => 'Yes — {kids_meal} is {kids_meal_price}, cooked in the same pots as everything else, and there are high chairs and boosters by the door. Ask for one when you arrive or note it in the booking.'],
                        ['question' => 'Can we bring a birthday cake?', 'answer' => 'Please do. Hand it to the front desk when you arrive and it will wait in the fridge until you give the nod. We bring plates, forks and a match for the candles — no cake fee.'],
                        ['question' => 'We are more than ten — what is the long table?', 'answer' => 'Sixteen feet of oak by the window, seating sixteen. It is booked by phone rather than online, so call {phone} and we will hold it and plan the timing with you.'],
                        ['question' => 'Can the kitchen handle allergies and diets?', 'answer' => 'Yes, with a note in the booking. The menu is cooked to order, so leaving something out or swapping a side is normal here, not a special request.'],
                    ],
                ]],
            ],
        ];
    }
}
