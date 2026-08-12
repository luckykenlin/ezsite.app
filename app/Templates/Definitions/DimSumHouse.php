<?php

declare(strict_types=1);

namespace App\Templates\Definitions;

use App\Design\ColorPalette;
use App\Design\StylePreset;
use App\Enums\PhotoCategory;
use App\StockPhotos\PhotoOrientation;
use App\Templates\DemoProfile;
use App\Templates\PhotoQuery;
use App\Templates\TemplateDefinition;
use App\Templates\TemplateField;

/**
 * Cantonese tea house — the menu-card showcase.
 *
 * The second Chinese-restaurant shape, deliberately not a variation on the
 * first: {@see ChineseRestaurant} is a family kitchen whose menu is a short
 * list of what the wok is known for, while a dim sum house's menu IS the
 * establishment — dozens of small dishes a table orders in waves — so the
 * home page is built around the offerings `menu-card` variant at full size:
 * course tabs, dietary flags, and the Peking duck in the spotlight.
 *
 * WarmCraft for its editorial serif and per-block opinions, but on the Brand
 * palette rather than WarmSand: the preset's terracotta cannot say "banquet",
 * so the brand hexes below — lacquer red, ink, true gold — become the palette
 * itself, which is exactly the "one system, two different restaurants" case
 * the TemplateDefinition::tokens() docblock pitches. The menu card mixes its
 * paper from base and accent, so the gold turns its frame to gilt edges.
 */
final readonly class DimSumHouse
{
    public static function definition(): TemplateDefinition
    {
        return new TemplateDefinition(
            preset: StylePreset::WarmCraft,
            brandPrimary: '#8B1E1E',
            brandSecondary: '#241E17',
            brandAccent: '#C9A24B',
            category: 'dim sum restaurant',
            chrome: self::chrome(),
            pages: [self::home(), self::about(), self::contact()],
            photoQueries: self::photoQueries(),
            demoProfile: self::demoProfile(),
            extraFields: self::extraFields(),
            palette: ColorPalette::Brand,
        );
    }

    private static function demoProfile(): DemoProfile
    {
        return new DemoProfile(
            name: 'Golden Lotus',
            tagline: 'Cantonese dim sum, made by hand since 1995',
            description: 'A tea house in the old style: bamboo steamers folded before dawn, tea poured before you ask, and a Peking duck that takes a day to earn its skin.',
            city: 'San Francisco',
            phone: '(415) 555-0128',
            email: 'hello@goldenlotus.example',
            addressLine1: '123 Silk Road Avenue',
            state: 'CA',
            postalCode: '94108',
            latitude: 37.7941,
            longitude: -122.4078,
        );
    }

    /**
     * @return list<TemplateField>
     */
    private static function extraFields(): array
    {
        return [
            new TemplateField('dish_one', 'Har Gow'),
            new TemplateField('dish_one_price', '$8'),
            new TemplateField('dish_two', 'Xiao Long Bao'),
            new TemplateField('dish_two_price', '$9'),
            new TemplateField('dish_three', 'Imperial Peking Duck'),
            new TemplateField('dish_three_price', '$45'),
        ];
    }

    /**
     * @return list<PhotoQuery>
     */
    private static function photoQueries(): array
    {
        return [
            new PhotoQuery('dim sum bamboo steamer baskets', category: PhotoCategory::FoodDrink, count: 4),
            new PhotoQuery('peking duck carved restaurant', category: PhotoCategory::FoodDrink, count: 3),
            new PhotoQuery('chinese tea ceremony teapot pouring', category: PhotoCategory::FoodDrink, count: 3),
            new PhotoQuery('chinese restaurant banquet dining room', category: PhotoCategory::Interior, count: 4),
            new PhotoQuery('dim sum chef folding dumplings', PhotoOrientation::Portrait, PhotoCategory::People, 3),
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
                    ['label' => 'Menu', 'url' => '/#menu'],
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
                'note' => '{business_name} — {city}. The steamers stop half an hour before we close; the teapot does not.',
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
            'meta_description' => '{business_name} in {city} — {tagline}. Dim sum, banquet mains and a table worth booking.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'full-bleed-overlay', 'data' => [
                    'eyebrow' => 'Authentic Cantonese cuisine',
                    'heading' => 'Taste the tradition',
                    'subheading' => 'A culinary journey through the tea houses of Guangdong, in a room built for long lunches — every basket folded by hand, every pot refilled before you ask.',
                    'cta_label' => 'See the menu',
                    'cta_url' => '/#menu',
                    'image_query' => 'chinese restaurant banquet dining room',
                ]],
                ['type' => 'prose', 'variant' => 'side-heading', 'tone' => 'muted', 'data' => [
                    'heading' => 'Crafting memories since 1995',
                    'paragraphs' => [
                        ['text' => 'The dim sum here is not a menu category, it is the reason the doors open. The folding starts before dawn — har gow pleated nineteen times because eighteen is somebody else\'s har gow — and what does not pass the head chef\'s eye goes to the family table, not to yours.'],
                        ['text' => 'The spices come from the markets of Guangzhou, the ducks take a full day of attention, and the tea is poured the moment you sit. Everything else is conversation.'],
                    ],
                ]],
                ['type' => 'offerings', 'variant' => 'menu-card', 'data' => [
                    'heading' => 'Our menu',
                    'intro' => 'Traditional recipes passed down through generations, prepared with modern precision.',
                    'items' => [
                        ['group' => 'Dim sum', 'name' => '{dish_one}', 'price' => '{dish_one_price}', 'description' => 'Steamed crystal shrimp dumplings with bamboo shoots.', 'gluten_free' => true],
                        ['group' => 'Dim sum', 'name' => 'Siu Mai', 'price' => '$8', 'description' => 'Open-topped pork and shrimp dumplings, topped with roe.'],
                        ['group' => 'Dim sum', 'name' => 'Char Siu Bao', 'price' => '$7', 'description' => 'Fluffy steamed buns filled with honey barbecue pork.'],
                        ['group' => 'Dim sum', 'name' => '{dish_two}', 'price' => '{dish_two_price}', 'description' => 'Shanghai soup dumplings filled with pork and rich broth.'],
                        ['group' => 'Dim sum', 'name' => 'Scallion Pancakes', 'price' => '$6', 'description' => 'Crisp, flaky flatbread layered with green onion.', 'vegetarian' => true],
                        ['group' => 'Soups', 'name' => 'Hot & Sour Soup', 'price' => '$10', 'description' => 'Tofu, wood ear mushrooms, bamboo shoots and egg.', 'spicy' => true, 'gluten_free' => true],
                        ['group' => 'Soups', 'name' => 'Wonton Soup', 'price' => '$10', 'description' => 'Shrimp wontons in a rich chicken broth with scallions.'],
                        ['group' => 'Soups', 'name' => 'West Lake Beef Soup', 'price' => '$12', 'description' => 'Minced beef chowder with cilantro and egg white.', 'gluten_free' => true],
                        ['group' => 'Mains & rice', 'name' => '{dish_three}', 'price' => '{dish_three_price}', 'description' => 'Our signature, prepared over twenty-four hours: amber skin, hand-made pancakes, julienned cucumber, scallion and our own hoisin blend.', 'is_featured' => true],
                        ['group' => 'Mains & rice', 'name' => 'Kung Pao Chicken', 'price' => '$18', 'description' => 'Wok-fired with peanuts, dried chillies and Sichuan pepper.', 'spicy' => true],
                        ['group' => 'Mains & rice', 'name' => 'Mapo Tofu', 'price' => '$16', 'description' => 'Silken tofu in a chilli-bean sauce that does not apologise.', 'spicy' => true, 'gluten_free' => true],
                        ['group' => 'Mains & rice', 'name' => 'Honey Walnut Shrimp', 'price' => '$22', 'description' => 'Crisp shrimp with candied walnuts and cream.', 'gluten_free' => true],
                        ['group' => 'Mains & rice', 'name' => 'Beef Chow Fun', 'price' => '$17', 'description' => 'Wide rice noodles, flank steak, bean sprouts, proper wok breath.', 'gluten_free' => true],
                        ['group' => 'Mains & rice', 'name' => 'Yangzhou Fried Rice', 'price' => '$15', 'description' => 'Shrimp, barbecue pork, egg and peas, tossed to order.', 'gluten_free' => true],
                        ['group' => 'Desserts', 'name' => 'Mango Sago', 'price' => '$9', 'description' => 'Chilled mango cream with sago pearls and pomelo.', 'vegetarian' => true, 'gluten_free' => true],
                        ['group' => 'Desserts', 'name' => 'Egg Tarts', 'price' => '$8', 'description' => 'Flaky pastry filled with warm egg custard.', 'vegetarian' => true],
                        ['group' => 'Desserts', 'name' => 'Sesame Balls', 'price' => '$6', 'description' => 'Fried glutinous rice, red bean paste inside.', 'vegetarian' => true, 'gluten_free' => true],
                    ],
                ]],
                ['type' => 'cta', 'variant' => 'banner', 'tone' => 'accent', 'data' => [
                    'heading' => 'A taste you\'ll remember',
                    'body' => 'An intimate dinner for two or a banquet for twenty — there is a table with your name on it, and a teapot already warming.',
                    'cta_label' => 'Book a table',
                    'cta_url' => '/contact',
                    'secondary_label' => 'Our story',
                    'secondary_url' => '/about',
                ]],
                ['type' => 'gallery', 'variant' => 'grid', 'tone' => 'base', 'data' => [
                    'heading' => 'A visual feast',
                    'images' => [
                        ['alt' => 'Steamer baskets arriving at the table'],
                        ['alt' => 'The duck, carved tableside'],
                        ['alt' => 'Tea poured the moment you sit'],
                        ['alt' => 'The dining room set for a banquet'],
                        ['alt' => 'Dumplings folded before dawn'],
                        ['alt' => 'The kitchen mid-service'],
                    ],
                    'image_query' => 'dim sum bamboo steamer baskets',
                ]],
                ['type' => 'testimonials', 'variant' => 'grid', 'tone' => 'muted', 'data' => [
                    'heading' => 'From the regulars',
                    'testimonials' => [
                        ['quote' => 'We come for the har gow and stay for the third pot of tea. Nobody has ever hurried us out.', 'author' => 'Priscilla T.', 'role' => 'Sunday regular'],
                        ['quote' => 'The duck needs ordering a day ahead. Order the duck a day ahead.', 'author' => 'Marcus W.', 'role' => 'Learned the hard way'],
                    ],
                ]],
                ['type' => 'visit', 'data' => [
                    'heading' => 'Visit us',
                    'intro' => 'Lunch through dinner, seven days. The dim sum carts run until mid-afternoon; the full kitchen takes over from there.',
                    'show_hours' => true,
                    'show_map' => true,
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
            'meta_description' => 'How {business_name} came to be — one family, one tea house, thirty years of folded dumplings.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'centered-minimal', 'data' => [
                    'eyebrow' => 'Since 1995',
                    'heading' => 'A tea house in the old style',
                    'subheading' => 'The recipes crossed an ocean in a notebook. The notebook is still in the kitchen.',
                ]],
                ['type' => 'prose', 'variant' => 'stacked', 'data' => [
                    'heading' => 'How it started',
                    'paragraphs' => [
                        ['text' => 'The founder learned to fold dumplings standing on a crate in a Guangzhou tea house, because the bench was built for grown-ups and the trade was not. Fifty years later the crate is gone and the standard is not: every pleat by hand, every morning, before the doors open.'],
                        ['text' => 'Food here is not just sustenance — it is a bridge between generations. The dining room has hosted first dates and their weddings, red-egg parties and retirement banquets, and one proposal that had the whole floor pretending not to watch.'],
                        ['text' => 'The kitchen has grown, the carts have multiplied, and the duck still takes a full day. Some things should not scale.'],
                    ],
                ]],
                ['type' => 'stats', 'tone' => 'muted', 'data' => [
                    'heading' => 'By the numbers',
                    'stats' => [
                        ['value' => '30', 'label' => 'Years of morning folds'],
                        ['value' => '19', 'label' => 'Pleats in a proper har gow'],
                        ['value' => '24', 'label' => 'Hours to earn a duck\'s skin'],
                    ],
                ]],
                ['type' => 'gallery', 'variant' => 'masonry', 'data' => [
                    'heading' => 'Behind the steam',
                    'images' => [
                        ['alt' => 'Hands pleating har gow'],
                        ['alt' => 'The tea master\'s station'],
                        ['alt' => 'Steamers stacked to the pass'],
                        ['alt' => 'The notebook the recipes came in'],
                    ],
                    'image_query' => 'dim sum chef folding dumplings',
                ]],
                ['type' => 'cta', 'variant' => 'banner', 'data' => [
                    'heading' => 'Come hungry, leave slowly',
                    'body' => 'The carts start rolling at eleven. The corner table sees the whole room — ask for it.',
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
            'meta_description' => 'Opening hours, address, phone and table bookings for {business_name} in {city}.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'centered-minimal', 'data' => [
                    'eyebrow' => '{city}',
                    'heading' => 'Visit us',
                    'subheading' => 'Walk in for the carts, book ahead for the banquet room — either way the tea is already on.',
                ]],
                ['type' => 'contact', 'tone' => 'base', 'spacing' => 'airy', 'data' => [
                    'heading' => 'Where to find us',
                    'intro' => 'Street parking is honest after seven; the garage on the corner is honest always.',
                    'show_form' => true,
                    'success_message' => 'Thank you — we will get back to you before the next pot steeps.',
                ]],
                ['type' => 'reservation', 'tone' => 'muted', 'spacing' => 'airy', 'data' => [
                    'heading' => 'Reserve a table',
                    'intro' => 'Tell us when and how many. Banquet menus and the round tables start at eight guests; the duck appreciates a day\'s notice.',
                    'max_party_size' => 20,
                    'button_label' => 'Request a reservation',
                    'success_message' => 'Got it — we will call you back to confirm the table.',
                    'fine_print' => 'A full banquet, or more than twenty? Call {phone} and ask for the events book.',
                ]],
                ['type' => 'faq', 'tone' => 'base', 'data' => [
                    'heading' => 'Before you come',
                    'questions' => [
                        ['question' => 'Do I need to book for dim sum?', 'answer' => 'Weekdays, no — walk in and follow the steam. Weekend mornings the queue starts before we do, so a booking saves you the wait.'],
                        ['question' => 'Can you cook around allergies?', 'answer' => 'Yes — the menu marks spicy, vegetarian and gluten-free dishes, and the kitchen will steer you around anything else. Tell whoever seats you.'],
                        ['question' => 'How does the Peking duck work?', 'answer' => 'Order it a day ahead by phone. It is carved at the table in two courses, and it does not wait well — come on time.'],
                    ],
                ]],
            ],
        ];
    }
}
