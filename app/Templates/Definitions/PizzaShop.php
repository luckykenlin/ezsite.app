<?php

declare(strict_types=1);

namespace App\Templates\Definitions;

use App\Design\ColorPalette;
use App\Design\FontPair;
use App\Design\StylePreset;
use App\Enums\PhotoCategory;
use App\StockPhotos\PhotoOrientation;
use App\Templates\DemoProfile;
use App\Templates\PhotoQuery;
use App\Templates\TemplateDefinition;
use App\Templates\TemplateField;

/**
 * Neighbourhood pizzeria — the preset-variation pilot.
 *
 * Structurally this is {@see ChineseRestaurant}: a shopfront with a
 * Location-bound contact section, priced offerings driven by wizard answers,
 * and a photograph doing the selling above the fold. What makes it worth
 * shipping as a second restaurant is that it runs on the SAME preset with two
 * tokens swapped.
 *
 * WarmCraft, with `palette: Sunset` and `fontPair: FriendlyRounded`. Everything
 * that gives WarmCraft its manner is untouched — large radii, spacious rhythm,
 * curved section dividers, flat accents — so the two sites share a posture and
 * a sense of room. The swap only changes temperature: warm sand and an elegant
 * serif read as a quiet dining room with cloth on the tables, while sunset over
 * a rounded face reads as a counter, a queue and an oven you can see from the
 * door. Same system, two different restaurants, and the preset marker survives
 * the override so both keep WarmCraft's per-block opinions.
 */
final readonly class PizzaShop
{
    public static function definition(): TemplateDefinition
    {
        return new TemplateDefinition(
            preset: StylePreset::WarmCraft,
            brandPrimary: '#C2410C',
            brandSecondary: '#2B1B14',
            brandAccent: '#F2A65A',
            category: 'pizzeria',
            chrome: self::chrome(),
            pages: [self::home(), self::about(), self::contact()],
            photoQueries: self::photoQueries(),
            demoProfile: self::demoProfile(),
            extraFields: self::extraFields(),
            palette: ColorPalette::Sunset,
            fontPair: FontPair::FriendlyRounded,
        );
    }

    private static function demoProfile(): DemoProfile
    {
        return new DemoProfile(
            name: "Rosalie's",
            tagline: 'Wood-fired pizza, two streets from your door',
            description: 'A small corner pizzeria with one oven, a two-day dough and a menu short enough to read standing up.',
            city: 'Providence',
            phone: '(401) 555-0137',
            email: 'hello@rosalies.example',
            addressLine1: '218 Wickenden Street',
            state: 'RI',
            postalCode: '02903',
            timezone: 'America/New_York',
        );
    }

    /**
     * @return list<TemplateField>
     */
    private static function extraFields(): array
    {
        return [
            new TemplateField('pizza_one', 'The pizza you are known for', 'The Margherita', help: 'It leads the menu section, so pick the one you would want a first-timer to order.'),
            new TemplateField('pizza_one_price', 'Its price', '$16'),
            new TemplateField('pizza_two', 'A second pizza', 'Hot Soppressata & Honey'),
            new TemplateField('pizza_two_price', 'Its price', '$19'),
            new TemplateField('pizza_three', 'One more', 'Mushroom, Taleggio & Thyme'),
            new TemplateField('pizza_three_price', 'Its price', '$18'),
        ];
    }

    /**
     * @return list<PhotoQuery>
     */
    private static function photoQueries(): array
    {
        return [
            new PhotoQuery('wood fired pizza oven flames', category: PhotoCategory::FoodDrink, count: 4),
            new PhotoQuery('neapolitan pizza close up', category: PhotoCategory::FoodDrink, count: 4),
            new PhotoQuery('pizza dough hands flour', category: PhotoCategory::FoodDrink, count: 3),
            new PhotoQuery('small pizzeria interior counter', category: PhotoCategory::Interior, count: 4),
            new PhotoQuery('pizzaiolo portrait at the oven', PhotoOrientation::Portrait, PhotoCategory::People, 3),
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
                    ['label' => 'The oven', 'url' => '/about'],
                    ['label' => 'Visit', 'url' => '/contact'],
                ],
                'cta_label' => 'Order by phone',
                'cta_url' => 'tel:{phone}',
            ]],
            ['type' => 'footer', 'data' => [
                'variant' => 'columns',
                'nav_links' => [
                    ['label' => 'The oven', 'url' => '/about'],
                    ['label' => 'Visit', 'url' => '/contact'],
                ],
                'note' => '{business_name} — {city}. When the dough runs out, we close.',
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
            'meta_description' => '{business_name} in {city} — {tagline}. Eat in, take away, or ring ahead.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'full-bleed-overlay', 'data' => [
                    'eyebrow' => '{city}',
                    'heading' => '{tagline}',
                    'subheading' => 'Ninety seconds over live fire, then straight onto the counter. If you can see the oven from where you are standing, you are close enough.',
                    'cta_label' => "See tonight's menu",
                    'cta_url' => '/#menu',
                    'image_query' => 'wood fired pizza oven flames',
                ]],
                ['type' => 'offerings', 'tone' => 'base', 'spacing' => 'airy', 'data' => [
                    'heading' => 'What comes out of the oven',
                    'intro' => 'Six pizzas most weeks, three of them always. Ask what the specials board says before you decide.',
                    'items' => [
                        ['group' => 'Pizza', 'name' => '{pizza_one}', 'price' => '{pizza_one_price}', 'description' => 'The one to order first. Three ingredients, nowhere to hide, and the reason people walk past two other places to get here.'],
                        ['group' => 'Pizza', 'name' => '{pizza_two}', 'price' => '{pizza_two_price}', 'description' => 'Sweet, hot and a little bit greedy. Regulars order it and then pretend they were going to share.'],
                        ['group' => 'Pizza', 'name' => '{pizza_three}', 'price' => '{pizza_three_price}', 'description' => 'The quiet one on the menu. It wins people over by about the third slice.'],
                    ],
                ]],
                ['type' => 'prose', 'variant' => 'side-heading', 'tone' => 'muted', 'data' => [
                    'heading' => 'It starts two days early',
                    'paragraphs' => [
                        ['text' => 'The dough is mixed on Monday for Wednesday. Two days cold in the fridge is what gives it the blistered edge and the flavour you get before anything is put on top.'],
                        ['text' => 'The rest is unremarkable on purpose: tinned San Marzano, fior di latte torn by hand, oak in the oven and someone watching it turn. No shortcuts, mostly because none of them taste as good.'],
                    ],
                ]],
                ['type' => 'gallery', 'variant' => 'masonry', 'tone' => 'base', 'data' => [
                    'heading' => 'The corner shop',
                    'images' => [
                        ['alt' => 'The oven going at the start of service'],
                        ['alt' => 'A pizza coming off the peel'],
                        ['alt' => 'The counter and the queue'],
                        ['alt' => 'Tables by the window'],
                    ],
                    'image_query' => 'small pizzeria interior counter',
                ]],
                ['type' => 'testimonials', 'variant' => 'grid', 'tone' => 'muted', 'data' => [
                    'heading' => 'From up the road',
                    'testimonials' => [
                        ['quote' => 'We moved onto this street six years ago and have eaten here nearly every Friday since. My son learned to count using the specials board.', 'author' => 'Priya N.', 'role' => 'Two doors down'],
                        ['quote' => 'I asked for a pizza without the chilli and got a small lecture, then exactly what I asked for. Both were correct.', 'author' => 'Tomas R.', 'role' => 'Regular'],
                    ],
                ]],
                ['type' => 'contact', 'tone' => 'base', 'spacing' => 'airy', 'data' => [
                    'heading' => 'Where we are',
                    'intro' => 'Walk in and wait by the oven, or ring ahead and it will be boxed when you get here.',
                    'show_form' => false,
                ]],
                ['type' => 'cta', 'variant' => 'banner', 'tone' => 'accent', 'data' => [
                    'heading' => 'Fire is already on',
                    'body' => 'Ring the shop and give us fifteen minutes. Someone always picks up between pizzas.',
                    'cta_label' => 'Call {phone}',
                    'cta_url' => 'tel:{phone}',
                    'secondary_label' => 'About the oven',
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
            'title' => 'The oven',
            'meta_description' => 'How {business_name} makes its dough, and who is on the peel.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'centered-minimal', 'data' => [
                    'eyebrow' => 'One oven, one street',
                    'heading' => 'Built round the fire',
                    'subheading' => 'A brick oven, a fridge full of slow dough, and a menu we keep short so that all of it is good.',
                ]],
                ['type' => 'prose', 'variant' => 'stacked', 'data' => [
                    'heading' => 'How we ended up here',
                    'paragraphs' => [
                        ['text' => 'We took over a shuttered sandwich shop with a good corner and a bad ceiling, put an oven where the fryer had been, and opened with four pizzas because that was all we could make properly.'],
                        ['text' => 'It is six now, and two of them change with whatever the market has. That is as much menu as one oven can do without something slipping.'],
                        ['text' => 'The people who work here have mostly been here a while. On a busy Friday there are four of us behind the counter and one of us is always covered in flour.'],
                    ],
                ]],
                ['type' => 'steps', 'variant' => 'timeline', 'tone' => 'muted', 'data' => [
                    'heading' => 'From flour to your hands',
                    'intro' => 'Four days a week this runs on a loop, and it is the only part of the shop that never changes.',
                    'steps' => [
                        ['title' => 'Mix', 'description' => 'Flour, water, salt and a little starter, worked until it is smooth and then left alone.'],
                        ['title' => 'Rest', 'description' => 'Forty-eight hours cold. This is the step that cannot be rushed, and the one everybody tries to rush.'],
                        ['title' => 'Stretch', 'description' => 'By hand, on the counter, in front of you. Rolling pins flatten the air out of the rim.'],
                        ['title' => 'Fire', 'description' => 'Ninety seconds at the mouth of the oven, turned twice, out before the base goes stiff.'],
                    ],
                ]],
                ['type' => 'stats', 'tone' => 'muted', 'data' => [
                    'heading' => 'The shop in numbers',
                    'stats' => [
                        ['value' => '48', 'label' => 'Hours the dough rests'],
                        ['value' => '900', 'label' => 'Degrees at the oven mouth'],
                        ['value' => '6', 'label' => 'Pizzas on the board'],
                    ],
                ]],
                ['type' => 'gallery', 'variant' => 'grid', 'data' => [
                    'heading' => 'Before the doors open',
                    'images' => [
                        ['alt' => 'Dough balls proving in trays'],
                        ['alt' => 'Stretching a base by hand'],
                        ['alt' => 'The first fire of the day'],
                    ],
                    'image_query' => 'pizza dough hands flour',
                ]],
                ['type' => 'cta', 'variant' => 'banner', 'data' => [
                    'heading' => 'Come and stand by the oven',
                    'body' => 'It is the warmest spot in the shop, and the wait goes quickly from there.',
                    'cta_label' => 'Find us',
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
            'meta_description' => 'Hours, address and phone number for {business_name} in {city}.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'centered-minimal', 'data' => [
                    'eyebrow' => '{city}',
                    'heading' => 'Come by',
                    'subheading' => 'Eat in at one of the six tables, take it away in a box, or ring ahead and skip the queue.',
                ]],
                ['type' => 'contact', 'tone' => 'base', 'spacing' => 'airy', 'data' => [
                    'heading' => 'Find the shop',
                    'intro' => 'We are on the corner with the red awning. Parking is easier after seven, and the bus stops outside.',
                    'show_form' => true,
                    'success_message' => 'Got it — we will get back to you between services.',
                ]],
                ['type' => 'faq', 'tone' => 'muted', 'data' => [
                    'heading' => 'Worth knowing first',
                    'questions' => [
                        ['question' => 'Do you take bookings?', 'answer' => 'Not for the tables — they are first come, first served. For parties of eight or more, call {phone} and we will work something out.'],
                        ['question' => 'Do you deliver?', 'answer' => 'No. A pizza this thin does not survive a car journey, and we would rather you had it hot at the counter.'],
                        ['question' => 'Is there anything for vegetarians?', 'answer' => 'Half the board, always, and the specials lean that way in summer. Vegan cheese is there if you ask.'],
                        ['question' => 'Can I order gluten free?', 'answer' => 'We make a gluten-free base, but it shares an oven with everything else, so it is not safe for coeliacs.'],
                    ],
                ]],
            ],
        ];
    }
}
