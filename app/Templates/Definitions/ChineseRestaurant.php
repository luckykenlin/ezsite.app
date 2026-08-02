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
 * Family Chinese restaurant — the bind-heavy pilot.
 *
 * Chosen as one of the two shapes to prove first because it exercises
 * everything a local-business template needs: a Location-bound contact
 * section, opening-hours-shaped copy, priced offerings driven by wizard
 * answers, and a photo-led hero. If the {@see TemplateDefinition} shape works
 * here it works for every trade with a front door.
 *
 * WarmCraft, unmodified: elegant serif over warm sand is the closest the
 * preset library comes to a room with wooden tables in it, and the full-bleed
 * inverted hero gives the banquet-table photograph the whole first viewport.
 */
final readonly class ChineseRestaurant
{
    public static function definition(): TemplateDefinition
    {
        return new TemplateDefinition(
            preset: StylePreset::WarmCraft,
            brandPrimary: '#8C2F1F',
            brandSecondary: '#2F2A24',
            brandAccent: '#C8963E',
            category: 'chinese restaurant',
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
            name: 'Golden Bamboo',
            tagline: 'Home-style Cantonese cooking since 1994',
            description: 'A family kitchen serving the Cantonese dishes three generations grew up on — wok-fired to order, never held under a lamp.',
            city: 'Oakland',
            phone: '(510) 555-0142',
            email: 'hello@goldenbamboo.example',
            addressLine1: '812 Franklin Street',
            state: 'CA',
            postalCode: '94607',
        );
    }

    /**
     * @return list<TemplateField>
     */
    private static function extraFields(): array
    {
        return [
            new TemplateField('dish_one', 'Your signature dish', 'Salt & Pepper Prawns'),
            new TemplateField('dish_one_price', 'Its price', '$24'),
            new TemplateField('dish_two', 'A second favourite', 'Clay Pot Chicken Rice'),
            new TemplateField('dish_two_price', 'Its price', '$18'),
            new TemplateField('dish_three', 'One more', 'Beef Chow Fun'),
            new TemplateField('dish_three_price', 'Its price', '$17'),
        ];
    }

    /**
     * @return list<PhotoQuery>
     */
    private static function photoQueries(): array
    {
        return [
            new PhotoQuery('chinese restaurant banquet table', category: PhotoCategory::FoodDrink, count: 4),
            new PhotoQuery('cantonese dim sum steamer', category: PhotoCategory::FoodDrink, count: 4),
            new PhotoQuery('wok cooking flame kitchen', category: PhotoCategory::FoodDrink, count: 3),
            new PhotoQuery('chinese restaurant dining room interior', category: PhotoCategory::Interior, count: 4),
            new PhotoQuery('chef portrait restaurant kitchen', PhotoOrientation::Portrait, PhotoCategory::People, 3),
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
                    ['label' => 'Our story', 'url' => '/about'],
                    ['label' => 'Visit', 'url' => '/contact'],
                ],
                'cta_label' => 'Call to order',
                'cta_url' => 'tel:{phone}',
            ]],
            ['type' => 'footer', 'data' => [
                'variant' => 'columns',
                'nav_links' => [
                    ['label' => 'Our story', 'url' => '/about'],
                    ['label' => 'Visit', 'url' => '/contact'],
                ],
                'note' => '{business_name} — {city}. Kitchen closes half an hour before we do.',
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
            'meta_description' => '{business_name} in {city} — {tagline}. Dine in, take out, or call ahead.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'full-bleed-overlay', 'data' => [
                    'eyebrow' => '{city}',
                    'heading' => '{tagline}',
                    'subheading' => 'Three generations at one wok. Everything is fired to order, so it reaches the table the way it left the kitchen.',
                    'cta_label' => 'See the menu',
                    'cta_url' => '/#menu',
                    'image_query' => 'chinese restaurant banquet table',
                ]],
                ['type' => 'offerings', 'tone' => 'base', 'spacing' => 'airy', 'data' => [
                    'heading' => 'What people order',
                    'intro' => 'A short list of what the kitchen is known for. The full menu is longer — ask, and we will tell you what is good today.',
                    'items' => [
                        ['group' => 'From the wok', 'name' => '{dish_one}', 'price' => '{dish_one_price}', 'description' => 'The dish people come back for. Fired hot and fast, plated the moment it is done.'],
                        ['group' => 'From the wok', 'name' => '{dish_two}', 'price' => '{dish_two_price}', 'description' => 'Slow where it should be slow. A weeknight dinner that tastes like a Sunday one.'],
                        ['group' => 'From the wok', 'name' => '{dish_three}', 'price' => '{dish_three_price}', 'description' => 'Simple, and therefore unforgiving — which is why it is on this list.'],
                    ],
                ]],
                ['type' => 'prose', 'variant' => 'side-heading', 'tone' => 'muted', 'data' => [
                    'heading' => 'Cooked the way it was taught',
                    'paragraphs' => [
                        ['text' => 'Nothing here is held under a lamp. The stock is started in the morning, the sauces are made in-house, and the wok gets hot enough to do what a wok is for.'],
                        ['text' => 'It is not a complicated idea. It is just harder than the alternative, and it is the only way we know how to cook.'],
                    ],
                ]],
                ['type' => 'gallery', 'variant' => 'masonry', 'tone' => 'base', 'data' => [
                    'heading' => 'The dining room',
                    'images' => [
                        ['alt' => 'Dishes on a shared table'],
                        ['alt' => 'The kitchen mid-service'],
                        ['alt' => 'The dining room in the afternoon'],
                        ['alt' => 'A steamer basket coming off the heat'],
                    ],
                    'image_query' => 'chinese restaurant dining room interior',
                ]],
                ['type' => 'testimonials', 'variant' => 'grid', 'tone' => 'muted', 'data' => [
                    'heading' => 'From the neighbourhood',
                    'testimonials' => [
                        ['quote' => 'We have been coming here for eleven years. The salt and pepper prawns have never once been wrong.', 'author' => 'Marilyn C.', 'role' => 'Regular since 2015'],
                        ['quote' => 'It is the only place my grandmother will let us take her. That is the whole review.', 'author' => 'Danny L.', 'role' => 'Neighbour'],
                    ],
                ]],
                ['type' => 'contact', 'tone' => 'base', 'spacing' => 'airy', 'data' => [
                    'heading' => 'Find us',
                    'intro' => 'Walk in, or call ahead and it will be waiting.',
                    'show_form' => false,
                ]],
                ['type' => 'cta', 'variant' => 'banner', 'tone' => 'accent', 'data' => [
                    'heading' => 'Hungry now?',
                    'body' => 'Call the kitchen directly — we pick up between orders.',
                    'cta_label' => 'Call {phone}',
                    'cta_url' => 'tel:{phone}',
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
            'meta_description' => 'How {business_name} came to be, and who is in the kitchen.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'centered-minimal', 'data' => [
                    'eyebrow' => 'Since 1994',
                    'heading' => 'One family, one kitchen',
                    'subheading' => 'The recipes did not come from a book. They came from a grandmother who did not measure anything.',
                ]],
                ['type' => 'prose', 'variant' => 'stacked', 'data' => [
                    'heading' => 'How it started',
                    'paragraphs' => [
                        ['text' => 'We opened with eight tables and a menu written by hand. Most of the dishes on it are still on it, because the people who order them would notice.'],
                        ['text' => 'What has changed is everything around them — a bigger room, a second wok, and a queue on Friday nights we still find slightly unbelievable.'],
                        ['text' => 'What has not changed is who cooks. It is the same family, most nights, in the same kitchen.'],
                    ],
                ]],
                ['type' => 'stats', 'tone' => 'muted', 'data' => [
                    'heading' => 'By the numbers',
                    'stats' => [
                        ['value' => '30', 'label' => 'Years on the same street'],
                        ['value' => '3', 'label' => 'Generations at the wok'],
                        ['value' => '1', 'label' => 'Recipe book, unwritten'],
                    ],
                ]],
                ['type' => 'gallery', 'variant' => 'grid', 'data' => [
                    'heading' => 'In the kitchen',
                    'images' => [
                        ['alt' => 'The wok station at full heat'],
                        ['alt' => 'Prep before service'],
                        ['alt' => 'Steamers stacked and ready'],
                    ],
                    'image_query' => 'wok cooking flame kitchen',
                ]],
                ['type' => 'cta', 'variant' => 'banner', 'data' => [
                    'heading' => 'Come and eat',
                    'body' => 'We are open six days a week, and we save the corner table for people who ask nicely.',
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
            'meta_description' => 'Opening hours, address and phone number for {business_name} in {city}.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'centered-minimal', 'data' => [
                    'eyebrow' => '{city}',
                    'heading' => 'Come by',
                    'subheading' => 'Dine in, take out, or call ahead — whichever gets you fed fastest.',
                ]],
                ['type' => 'contact', 'tone' => 'base', 'spacing' => 'airy', 'data' => [
                    'heading' => 'Where to find us',
                    'intro' => 'Street parking is easier after seven. Large tables are worth calling about.',
                    'show_form' => true,
                    'success_message' => 'Thank you — we will call you back shortly.',
                ]],
                ['type' => 'faq', 'tone' => 'muted', 'data' => [
                    'heading' => 'Before you come',
                    'questions' => [
                        ['question' => 'Do you take bookings?', 'answer' => 'For six or more, yes — call us and we will hold a table. Smaller groups are walk-in.'],
                        ['question' => 'Is there anything for vegetarians?', 'answer' => 'A good half of the menu, and the kitchen will adapt most of the rest. Just ask when you order.'],
                        ['question' => 'Can I order to collect?', 'answer' => 'Always. Call {phone} and give us twenty minutes.'],
                    ],
                ]],
            ],
        ];
    }
}
