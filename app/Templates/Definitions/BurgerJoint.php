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
 * Counter-service burger joint — the loud one.
 *
 * Where {@see ChineseRestaurant} proves the quiet, bind-heavy shape of a
 * neighbourhood restaurant, this proves the same machinery survives being
 * shouted. Same priced offerings, same Location-bound contact section, but the
 * art direction is a stacked burger at full bleed with type sitting on top of
 * it — the layout most counter-service trades actually want and the one that
 * breaks first if the hero cannot carry a photograph on its own.
 *
 * PlayfulFriendly, unmodified: Sunset over rounded type, `RadiusScale::Full` on
 * every button and card, and Peak dividers between bands. It is the only preset
 * in the library whose voice is already casual — nothing here needs to look
 * expensive, it needs to look like a place that hands you a tray. Overriding
 * the tokens would only make it politer, which is the wrong direction for a
 * business whose menu board is the design.
 */
final readonly class BurgerJoint
{
    public static function definition(): TemplateDefinition
    {
        return new TemplateDefinition(
            preset: StylePreset::PlayfulFriendly,
            brandPrimary: '#D9342B',
            brandSecondary: '#2B1A12',
            brandAccent: '#F2A81D',
            category: 'burger restaurant',
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
            name: 'Smash Alley',
            tagline: 'Griddle-smashed burgers and fries worth the extra napkins',
            description: 'A flat-top, a counter and a short menu. Every patty is smashed to order for the crispy edges, and nothing sits under a heat lamp waiting for you.',
            city: 'Portland',
            phone: '(503) 555-0164',
            email: 'hey@smashalley.example',
            addressLine1: '2214 SE Division Street',
            state: 'OR',
            postalCode: '97202',
        );
    }

    /**
     * @return list<TemplateField>
     */
    private static function extraFields(): array
    {
        return [
            new TemplateField('burger_one', 'The Alley Double'),
            new TemplateField('burger_one_price', '$12'),
            new TemplateField('burger_two', 'Smoked Bacon Smash'),
            new TemplateField('burger_two_price', '$14'),
            new TemplateField('burger_three', 'The Green Chile Melt'),
            new TemplateField('burger_three_price', '$13'),
        ];
    }

    /**
     * @return list<PhotoQuery>
     */
    private static function photoQueries(): array
    {
        return [
            new PhotoQuery('stacked double cheeseburger close up', category: PhotoCategory::FoodDrink, count: 4),
            new PhotoQuery('burger restaurant counter interior', category: PhotoCategory::Interior, count: 4),
            new PhotoQuery('crispy french fries basket close up', category: PhotoCategory::FoodDrink, count: 3),
            new PhotoQuery('burger patties smashed on flat top grill', category: PhotoCategory::FoodDrink, count: 4),
            new PhotoQuery('line cook portrait burger kitchen', PhotoOrientation::Portrait, PhotoCategory::People, 3),
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
                    ['label' => 'Menu', 'url' => '/'],
                    ['label' => 'The joint', 'url' => '/about'],
                    ['label' => 'Find us', 'url' => '/contact'],
                ],
                'cta_label' => 'Order pickup',
                'cta_url' => 'tel:{phone}',
            ]],
            ['type' => 'footer', 'data' => [
                'variant' => 'columns',
                'nav_links' => [
                    ['label' => 'The joint', 'url' => '/about'],
                    ['label' => 'Find us', 'url' => '/contact'],
                ],
                'note' => "{business_name} — {city}. Griddle goes cold at ten, so do not be the eleven o'clock hero.",
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
            'meta_description' => '{business_name} in {city} — {tagline}. Smashed to order, eat in or take it to go.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'full-bleed-overlay', 'data' => [
                    'eyebrow' => '{city}',
                    'heading' => 'Smashed, not shaped',
                    'subheading' => 'Fresh beef on a screaming flat-top, pressed once, cheese on while it is still loud. Two minutes, start to tray.',
                    'cta_label' => 'See the menu',
                    'cta_url' => '/#menu',
                    'image_query' => 'stacked double cheeseburger close up',
                ]],
                ['type' => 'visit', 'data' => [
                    'heading' => 'Open now?',
                    'intro' => 'The grill runs until late. There is usually a line at seven — that is the cooking, not the queue.',
                    'show_hours' => true,
                ]],
                ['type' => 'offerings', 'tone' => 'base', 'spacing' => 'airy', 'data' => [
                    'heading' => 'The whole menu, more or less',
                    'intro' => 'Three burgers, fries, and a shake if you earned it. A short menu means nothing on it is an afterthought.',
                    'items' => [
                        ['group' => 'Burgers', 'name' => '{burger_one}', 'price' => '{burger_one_price}', 'description' => 'Two smashed patties, American cheese, pickles, house sauce. The one we would order.'],
                        ['group' => 'Burgers', 'name' => '{burger_two}', 'price' => '{burger_two_price}', 'description' => 'Thick-cut bacon smoked in-house, crisped on the griddle so it shatters instead of bending.'],
                        ['group' => 'Burgers', 'name' => '{burger_three}', 'price' => '{burger_three_price}', 'description' => 'Roasted green chile and a slab of jack that goes properly molten. Warm, not punishing.'],
                        ['group' => 'Sides', 'name' => 'Alley fries', 'price' => '$5', 'description' => 'Cut in the morning, fried twice, salted the second they come out of the oil.'],
                        ['group' => 'Shakes', 'name' => 'Malted vanilla', 'price' => '$7', 'description' => 'Thick enough that the straw is mostly decorative. Chocolate and seasonal too.'],
                    ],
                ]],
                ['type' => 'features', 'variant' => 'grid', 'tone' => 'muted', 'columns' => 'three', 'data' => [
                    'heading' => 'Three things we are annoying about',
                    'intro' => 'None of it is complicated. All of it is the difference.',
                    'features' => [
                        ['title' => 'Beef ground daily', 'description' => 'Chuck and brisket from a butcher eight blocks away, ground that morning and never frozen.'],
                        ['title' => 'Buns baked local', 'description' => 'Potato buns delivered warm, toasted in the beef fat. A dry bun ruins an otherwise good burger.'],
                        ['title' => 'Cooked to order', 'description' => "Nothing is pre-smashed at four for the six o'clock rush. If there is a line, that is why."],
                    ],
                ]],
                ['type' => 'gallery', 'variant' => 'filmstrip', 'tone' => 'base', 'data' => [
                    'heading' => 'On the griddle',
                    'images' => [
                        ['alt' => 'Patties going down on the flat-top'],
                        ['alt' => 'Cheese melting over the smash'],
                        ['alt' => 'A basket of fries out of the fryer'],
                        ['alt' => 'The counter at lunchtime'],
                    ],
                    'image_query' => 'burger patties smashed on flat top grill',
                ]],
                ['type' => 'testimonials', 'variant' => 'carousel', 'tone' => 'muted', 'data' => [
                    'heading' => 'Word on the block',
                    'testimonials' => [
                        ['quote' => 'I drive past four burger places to get here and I would drive past six.', 'author' => 'Reggie A.', 'role' => 'Tuesday regular'],
                        ['quote' => 'My kid ordered the double, ate the whole thing, and has not stopped talking about it since March.', 'author' => 'Priya N.', 'role' => 'Lives around the corner'],
                        ['quote' => 'Ten minute wait at noon and worth every one of them. Get the fries.', 'author' => 'Marcus T.', 'role' => 'Works up the street'],
                    ],
                ]],
                ['type' => 'contact', 'tone' => 'base', 'spacing' => 'airy', 'data' => [
                    'show_hours' => false,
                    'heading' => 'Come get one',
                    'intro' => 'Walk up to the counter, or call ahead and we will have it bagged.',
                    'show_form' => false,
                ]],
                ['type' => 'cta', 'variant' => 'banner', 'tone' => 'accent', 'data' => [
                    'heading' => 'Hungry right now?',
                    'body' => 'Call it in, give us ten minutes, and skip the line entirely.',
                    'cta_label' => 'Call {phone}',
                    'cta_url' => 'tel:{phone}',
                    'secondary_label' => 'The joint',
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
            'title' => 'The joint',
            'meta_description' => 'How {business_name} started, who is on the griddle, and why the menu is so short.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'centered-minimal', 'data' => [
                    'eyebrow' => 'The joint',
                    'heading' => 'It started in a parking lot',
                    'subheading' => 'One griddle, one folding table, and a line that kept showing up.',
                ]],
                ['type' => 'prose', 'variant' => 'stacked', 'data' => [
                    'heading' => 'The short version',
                    'paragraphs' => [
                        ['text' => 'We spent two summers running a flat-top out of a trailer, selling one burger and one order of fries because that was all the space allowed. Turns out that was also all anybody wanted.'],
                        ['text' => 'When we finally got a room with a roof on it, the temptation was to add fifteen things to the menu. We added two. Everything else went into better beef, better buns, and a fryer that can actually keep up.'],
                        ['text' => 'The griddle from the trailer is still in the kitchen. It is scarred, it is uneven, and it makes a better crust than the new one.'],
                    ],
                ]],
                ['type' => 'stats', 'tone' => 'muted', 'data' => [
                    'heading' => 'Some numbers',
                    'stats' => [
                        ['value' => '3', 'label' => 'Burgers on the menu'],
                        ['value' => '2 min', 'label' => 'From smash to tray'],
                        ['value' => '8', 'label' => 'Blocks to the butcher'],
                    ],
                ]],
                ['type' => 'steps', 'variant' => 'list', 'data' => [
                    'heading' => 'What happens after you order',
                    'intro' => 'Four moves, in the same order, every single time.',
                    'steps' => [
                        ['title' => 'A ball of beef hits the steel', 'description' => 'Loose-packed, never pre-formed, straight from the fridge onto a griddle at full heat.'],
                        ['title' => 'One press, ten seconds', 'description' => 'Pressed once and hard, then left alone. Pressing twice squeezes out everything worth eating.'],
                        ['title' => 'Cheese on, bun on', 'description' => 'The cheese goes down while the patty is still going, so it melts instead of just sitting there.'],
                        ['title' => 'Wrapped and handed over', 'description' => 'It steams the bun on the way to you. That is the point of the paper.'],
                    ],
                ]],
                ['type' => 'gallery', 'variant' => 'grid', 'data' => [
                    'heading' => 'The room',
                    'images' => [
                        ['alt' => 'The counter and the menu board'],
                        ['alt' => 'Tables filling up at lunch'],
                        ['alt' => 'The pass, mid-rush'],
                    ],
                    'image_query' => 'burger restaurant counter interior',
                ]],
                ['type' => 'cta', 'variant' => 'banner', 'data' => [
                    'heading' => 'Enough reading',
                    'body' => "We are on {city}'s side of the street, and the griddle is already hot.",
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
            'title' => 'Find us',
            'meta_description' => 'Address, hours and phone number for {business_name} in {city}.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'centered-minimal', 'data' => [
                    'eyebrow' => '{city}',
                    'heading' => 'Come by',
                    'subheading' => 'Eat in, take it out, or call ahead — whichever gets you to the first bite quickest.',
                ]],
                ['type' => 'contact', 'tone' => 'base', 'spacing' => 'airy', 'data' => [
                    'heading' => 'Where we are',
                    'intro' => 'Counter service, no reservations. The rush is twelve to one — before or after is a calmer lunch.',
                    'show_form' => true,
                    'success_message' => 'Got it — we will get back to you today.',
                ]],
                ['type' => 'faq', 'tone' => 'muted', 'data' => [
                    'heading' => 'Things people ask at the counter',
                    'questions' => [
                        ['question' => 'Can I call ahead?', 'answer' => 'Please do. Ring {phone}, give us ten minutes, and it will be bagged and waiting with your name on it.'],
                        ['question' => 'Is there anything for vegetarians?', 'answer' => 'Yes — the same build on a mushroom patty, cooked on its own section of the griddle. Say the word when you order.'],
                        ['question' => 'Do you do big orders?', 'answer' => "Twelve burgers and up, with a day's notice. Email {email} and tell us how many mouths."],
                        ['question' => 'Is there parking?', 'answer' => 'A small lot behind the building and plenty of street after seven. Bikes lock up out front.'],
                    ],
                ]],
            ],
        ];
    }
}
