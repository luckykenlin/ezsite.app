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
 * Bubble tea shop — the product-shot pilot.
 *
 * A counter trade whose entire storefront is one cup photographed well, and a
 * menu that changes with the season. Where {@see ChineseRestaurant} proves the
 * bound-Location shape and {@see DesignerPortfolio} proves the no-shopfront
 * one, this proves the middle: a short priced menu that rotates, a walk-up
 * address, and a hero whose job is to hold a single drink at eye level.
 *
 * FreshModern, unmodified: geometric type over the green-tinted Forest palette
 * is the closest the preset library comes to a tiled counter with plants on it,
 * and its per-section appearances alternate muted and base bands down the page
 * — a shaded stripe behind the features and the gallery, clean white behind the
 * menu and the quotes. That rhythm is exactly what a pastel product shot needs:
 * every photograph lands on its own band instead of competing with the one
 * above it. So the hero is `left-text-right-image`, not full-bleed — a cup of
 * milk tea cropped to a banner reads as wallpaper, and at half-width it reads
 * as the thing you are about to buy.
 */
final readonly class BubbleTea
{
    public static function definition(): TemplateDefinition
    {
        return new TemplateDefinition(
            preset: StylePreset::FreshModern,
            brandPrimary: '#4E9B76',
            brandSecondary: '#26332C',
            brandAccent: '#F4C9DA',
            category: 'bubble tea shop',
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
            name: 'Cloudleaf Tea Bar',
            tagline: 'Tea brewed every two hours, pearls cooked every twenty minutes',
            description: 'A small bubble tea bar where the tea is loose-leaf, the syrup is made on site, and you pick your own sweetness down to the quarter.',
            city: 'Ann Arbor',
            phone: '(734) 555-0119',
            email: 'hello@cloudleaf.example',
            addressLine1: '218 South Fourth Avenue',
            state: 'MI',
            postalCode: '48104',
            timezone: 'America/Detroit',
            latitude: 42.2793,
            longitude: -83.7455,
        );
    }

    /**
     * @return list<TemplateField>
     */
    private static function extraFields(): array
    {
        return [
            new TemplateField('drink_one', 'Brown Sugar Boba Milk'),
            new TemplateField('drink_one_price', '$6.75'),
            new TemplateField('drink_two', 'Jasmine Milk Tea'),
            new TemplateField('drink_two_price', '$5.50'),
            new TemplateField('seasonal_drink', 'Yuzu Green Tea with Lychee Pearls'),
            new TemplateField('seasonal_drink_price', '$7.25'),
        ];
    }

    /**
     * @return list<PhotoQuery>
     */
    private static function photoQueries(): array
    {
        return [
            new PhotoQuery('pastel bubble tea drink', category: PhotoCategory::FoodDrink, count: 5),
            new PhotoQuery('boba tea shop interior counter', category: PhotoCategory::Interior, count: 4),
            new PhotoQuery('tapioca pearls close up', category: PhotoCategory::FoodDrink, count: 3),
            new PhotoQuery('iced tea flat lay pastel', category: PhotoCategory::FoodDrink, count: 4),
            new PhotoQuery('barista making bubble tea', PhotoOrientation::Portrait, PhotoCategory::People, 3),
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
                    ['label' => 'Menu', 'url' => '/'],
                    ['label' => 'How we make it', 'url' => '/about'],
                    ['label' => 'Visit', 'url' => '/contact'],
                ],
                'cta_label' => 'Order ahead',
                'cta_url' => 'tel:{phone}',
            ]],
            ['type' => 'footer', 'data' => [
                'variant' => 'columns',
                'nav_links' => [
                    ['label' => 'How we make it', 'url' => '/about'],
                    ['label' => 'Visit', 'url' => '/contact'],
                ],
                'note' => '{business_name} — {city}. Last pearl batch goes on half an hour before close.',
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
            'meta_description' => '{business_name} in {city} — {tagline}. Loose-leaf tea, pearls cooked to order, sweetness your way.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'left-text-right-image', 'data' => [
                    'eyebrow' => '{city}',
                    'heading' => '{tagline}',
                    'subheading' => 'No powders, no pre-mix, no pearls sitting in syrup since this morning. Pick your sugar, pick your ice, and it is in your hand in about four minutes.',
                    'cta_label' => 'See the menu',
                    'cta_url' => '/#menu',
                    'image_query' => 'pastel bubble tea drink',
                ]],
                ['type' => 'visit', 'data' => [
                    'heading' => 'Find the counter',
                    'intro' => 'Open from late morning. Order at the counter, or ring ahead if you are picking up for the office.',
                    'show_hours' => true,
                    'show_map' => true,
                ]],
                ['type' => 'offerings', 'spacing' => 'airy', 'data' => [
                    'heading' => 'On the board',
                    'intro' => 'Sweetness runs 0 to 100 in quarters, and nobody here will look at you strangely for asking for 25. Seasonal drinks change when the fruit does.',
                    'items' => [
                        ['group' => 'House favourites', 'name' => '{drink_one}', 'price' => '{drink_one_price}', 'description' => 'The one that goes out the door most. Syrup made on site each morning, poured down the cup so you can see the stripes.'],
                        ['group' => 'House favourites', 'name' => '{drink_two}', 'price' => '{drink_two_price}', 'description' => 'Loose-leaf, steeped strong enough to stand up to milk. Good hot, better over ice.'],
                        ['group' => 'This season', 'name' => '{seasonal_drink}', 'price' => '{seasonal_drink_price}', 'description' => 'On the board while it is at its best, then replaced by whatever is next. Ask what is coming.'],
                    ],
                ]],
                ['type' => 'features', 'variant' => 'grid', 'data' => [
                    'heading' => 'Four things we are stubborn about',
                    'intro' => 'None of it is complicated. All of it is the difference between a good cup and a sweet one.',
                    'features' => [
                        ['title' => 'Tea brewed every two hours', 'description' => 'Loose-leaf, timed, and dumped when the timer says so. Tea that has sat all afternoon tastes like it has.'],
                        ['title' => 'Pearls in twenty-minute rounds', 'description' => 'Cooked, rested, and used inside four hours. Chewy in the middle, never chalky, never gummy.'],
                        ['title' => 'Sugar in quarters', 'description' => 'Zero, 25, 50, 75, 100 — measured, not eyeballed, so the drink you liked last week tastes the same today.'],
                        ['title' => 'Milk you can choose', 'description' => 'Whole, oat, or fresh soy at no extra charge. Oat is the one we would pick with the brown sugar.'],
                    ],
                ]],
                ['type' => 'gallery', 'variant' => 'grid', 'data' => [
                    'heading' => 'The counter',
                    'images' => [
                        ['alt' => 'A brown sugar milk tea just after the pour'],
                        ['alt' => 'Pearls coming out of the pot'],
                        ['alt' => 'The seasonal board at the counter'],
                        ['alt' => 'Iced tea lined up in the window'],
                    ],
                    'image_query' => 'iced tea flat lay pastel',
                ]],
                ['type' => 'testimonials', 'variant' => 'carousel', 'data' => [
                    'heading' => 'What regulars say',
                    'testimonials' => [
                        ['quote' => 'I order 25 percent sugar and they have never once made it sweeter to be safe. That is the whole reason I walk the extra four blocks.', 'author' => 'Priya N.', 'role' => 'Two or three times a week'],
                        ['quote' => 'My kid asked for the yuzu one every day for a month and cried when it came off the board. They put it back for a weekend.', 'author' => 'Marcus O.', 'role' => 'Lives around the corner'],
                        ['quote' => 'I have had a lot of boba. These pearls are the only ones I have ever finished on purpose.', 'author' => 'Elena R.', 'role' => 'Studies here most evenings'],
                    ],
                ]],
                ['type' => 'contact', 'spacing' => 'airy', 'data' => [
                    'show_hours' => false,
                    'heading' => 'Where we are',
                    'intro' => 'Walk up and order, or call it in and skip the line.',
                    'show_form' => false,
                ]],
                ['type' => 'cta', 'variant' => 'banner', 'tone' => 'accent', 'data' => [
                    'heading' => 'Want it waiting?',
                    'body' => 'Call the counter and we will have it sealed and cold by the time you get here.',
                    'cta_label' => 'Call {phone}',
                    'cta_url' => 'tel:{phone}',
                    'secondary_label' => 'How we make it',
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
            'title' => 'How we make it',
            'meta_description' => 'How {business_name} brews its tea, cooks its pearls, and decides what goes on the seasonal board.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'centered-minimal', 'data' => [
                    'eyebrow' => 'Behind the counter',
                    'heading' => 'A short menu, made properly',
                    'subheading' => 'We would rather do twelve drinks well than forty badly, and the twelve change when the season does.',
                ]],
                ['type' => 'prose', 'variant' => 'stacked', 'data' => [
                    'heading' => 'Why the board keeps changing',
                    'paragraphs' => [
                        ['text' => 'We opened {business_name} because every bubble tea within walking distance came out of a powder tin, and you could taste the tin. Ours starts with leaves, a scale and a timer.'],
                        ['text' => 'The seasonal slot exists so the menu has somewhere to be curious. Strawberry in spring, yuzu when the citrus is good, roasted oolong the week it turns cold. If a special sells out three days running it usually earns a permanent spot.'],
                        ['text' => 'Everything else stays exactly where it is, because the person who orders the same drink every Tuesday should be able to.'],
                    ],
                ]],
                ['type' => 'steps', 'variant' => 'timeline', 'data' => [
                    'heading' => 'What happens after you order',
                    'intro' => 'Four minutes, more or less, and none of it happens before you ask.',
                    'steps' => [
                        ['title' => 'Tea off the batch', 'description' => 'Poured from whatever was brewed in the last two hours. If it is older than that it went down the drain.'],
                        ['title' => 'Sugar measured', 'description' => 'Your percentage, pumped not poured, so 50 means 50 every time.'],
                        ['title' => 'Pearls scooped warm', 'description' => 'Straight from the rest pot, still soft, into the bottom of the cup.'],
                        ['title' => 'Sealed and shaken', 'description' => 'Shaken cold, sealed flat, and handed over with a wide straw and a napkin you will probably need.'],
                    ],
                ]],
                ['type' => 'stats', 'data' => [
                    'heading' => 'The bar in four numbers',
                    'stats' => [
                        ['value' => '2 hrs', 'label' => 'Between tea brews'],
                        ['value' => '20 min', 'label' => 'Per pearl batch'],
                        ['value' => '5', 'label' => 'Sugar levels, measured'],
                        ['value' => '0', 'label' => 'Powdered mixes'],
                    ],
                ]],
                ['type' => 'gallery', 'variant' => 'grid', 'data' => [
                    'heading' => 'In the shop',
                    'images' => [
                        ['alt' => 'The counter mid-afternoon'],
                        ['alt' => 'Pearls resting in the pot'],
                        ['alt' => 'Loose-leaf tea on the scale'],
                    ],
                    'image_query' => 'boba tea shop interior counter',
                ]],
                ['type' => 'cta', 'variant' => 'banner', 'data' => [
                    'heading' => 'Come and try the seasonal one',
                    'body' => 'It will be different in a month, so this is the month to have it.',
                    'cta_label' => 'Find the shop',
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
            'meta_description' => 'Address, hours and phone number for {business_name} in {city}.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'centered-minimal', 'data' => [
                    'eyebrow' => '{city}',
                    'heading' => 'Come by',
                    'subheading' => 'Two doors down from the corner, green awning, plants in the window.',
                ]],
                ['type' => 'contact', 'spacing' => 'airy', 'data' => [
                    'heading' => 'Find the counter',
                    'intro' => 'Ordering ahead is the fastest route between three and five, when the line reaches the door.',
                    'show_form' => true,
                    'success_message' => 'Got it — we will get back to you today.',
                ]],
                ['type' => 'faq', 'data' => [
                    'heading' => 'Asked at the counter most days',
                    'intro' => 'The short answers. The longer ones are happily given in person.',
                    'questions' => [
                        ['question' => 'Can I order ahead?', 'answer' => 'Yes — call {phone} and give us five minutes. Bigger orders are worth calling a little earlier.'],
                        ['question' => 'What can I have without dairy?', 'answer' => 'Every milk tea on the board, with oat or fresh soy at no extra charge. The fruit teas have never had dairy in them.'],
                        ['question' => 'Is there anything without caffeine?', 'answer' => 'The fruit teas can be made on a herbal base, and the taro comes decaf by default. Just say so when you order.'],
                        ['question' => 'Do you do large orders for an office?', 'answer' => 'Often. Email {email} the day before with a headcount and we will have it boxed and labelled.'],
                    ],
                ]],
            ],
        ];
    }
}
