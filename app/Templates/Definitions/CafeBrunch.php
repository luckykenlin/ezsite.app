<?php

declare(strict_types=1);

namespace App\Templates\Definitions;

use App\Design\FontPair;
use App\Design\StylePreset;
use App\Enums\PhotoCategory;
use App\StockPhotos\PhotoOrientation;
use App\Templates\DemoProfile;
use App\Templates\PhotoQuery;
use App\Templates\TemplateDefinition;
use App\Templates\TemplateField;

/**
 * Neighbourhood café & weekend brunch — WarmCraft's morning shift.
 *
 * The third WarmCraft tenant, so the {@see PizzaShop} argument gets made a
 * third time: {@see ChineseRestaurant} runs the preset untouched and reads as
 * a family dining room; PizzaShop swaps palette and face and reads as a
 * counter with an oven behind it. This one swaps a single token —
 * `fontPair: QuietSerif`, Instrument Serif over Instrument Sans — and the
 * same large radii, spacious rhythm and curved dividers stop meaning dinner
 * service and start meaning morning light: a hand-lettered board over the
 * pastry case instead of signage over a queue. Same posture, third
 * temperature, and the preset marker survives so all three keep WarmCraft's
 * per-block opinions.
 *
 * It is also the library's first daytime trade, which changes what the
 * `reservation` block is for. {@see FineDining} sells a whole evening, so
 * booking owns the site; here weekdays are counter service and only weekend
 * brunch takes tables, so the form sits on the contact page with the
 * walk-in/bookable split said out loud in its intro.
 */
final readonly class CafeBrunch
{
    public static function definition(): TemplateDefinition
    {
        return new TemplateDefinition(
            preset: StylePreset::WarmCraft,
            brandPrimary: '#B4632E',
            brandSecondary: '#33291F',
            brandAccent: '#7C8F5A',
            category: 'cafe',
            chrome: self::chrome(),
            pages: [self::home(), self::about(), self::contact()],
            photoQueries: self::photoQueries(),
            demoProfile: self::demoProfile(),
            extraFields: self::extraFields(),
            fontPair: FontPair::QuietSerif,
        );
    }

    private static function demoProfile(): DemoProfile
    {
        return new DemoProfile(
            name: 'Marigold',
            tagline: 'Good mornings, served daily',
            description: 'A corner café with its own ovens: brunch until mid-afternoon, bakes on the counter until they run out, and coffee from a roaster four blocks away.',
            city: 'Portland',
            phone: '(503) 555-0176',
            email: 'hello@marigold.example',
            addressLine1: '3418 SE Hawthorne Boulevard',
            state: 'OR',
            postalCode: '97214',
            timezone: 'America/Los_Angeles',
            latitude: 45.5231,
            longitude: -122.6765,
            // Mornings, all seven days — the whole trade happens before three,
            // and the weekend starts an hour later because the bake does too.
            openingHours: [
                'monday' => ['07:00-15:00'],
                'tuesday' => ['07:00-15:00'],
                'wednesday' => ['07:00-15:00'],
                'thursday' => ['07:00-15:00'],
                'friday' => ['07:00-15:00'],
                'saturday' => ['08:00-15:00'],
                'sunday' => ['08:00-15:00'],
            ],
        );
    }

    /**
     * @return list<TemplateField>
     */
    private static function extraFields(): array
    {
        return [
            new TemplateField('brunch_one', 'Ricotta hotcakes, honeycomb butter'),
            new TemplateField('brunch_one_price', '$17'),
            new TemplateField('brunch_two', 'Green shakshuka, grilled sourdough'),
            new TemplateField('brunch_two_price', '$15'),
            new TemplateField('signature_coffee', 'Single-origin flat white'),
            new TemplateField('signature_coffee_price', '$5'),
        ];
    }

    /**
     * @return list<PhotoQuery>
     */
    private static function photoQueries(): array
    {
        return [
            new PhotoQuery('brunch plates overhead cafe table', category: PhotoCategory::FoodDrink, count: 4),
            new PhotoQuery('sunlit cafe interior morning light', category: PhotoCategory::Interior, count: 4),
            new PhotoQuery('pastry counter bakery case', category: PhotoCategory::FoodDrink, count: 3),
            new PhotoQuery('barista pouring latte art', PhotoOrientation::Portrait, PhotoCategory::People, 3),
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
                    ['label' => 'The mornings', 'url' => '/about'],
                    ['label' => 'Visit', 'url' => '/contact'],
                ],
                'cta_label' => 'Book brunch',
                'cta_url' => '/contact',
            ]],
            ['type' => 'footer', 'data' => [
                'variant' => 'soft',
                'nav_links' => [
                    ['label' => 'The mornings', 'url' => '/about'],
                    ['label' => 'Visit', 'url' => '/contact'],
                ],
                'note' => '{business_name} — {city}. Open with the light, gone by mid-afternoon.',
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
            'meta_description' => '{business_name} in {city} — {tagline}. Brunch, bakes and coffee, every morning of the week.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'left-text-right-image', 'data' => [
                    'eyebrow' => '{city}',
                    'heading' => '{tagline}',
                    'subheading' => 'Ovens on at five, doors at seven, hotcakes until the pans go into the sink. Weekdays you walk in; weekends you book, because everyone else already has.',
                    'cta_label' => 'See the menu',
                    'cta_url' => '/#menu',
                    'image_query' => 'brunch plates overhead cafe table',
                ]],
                ['type' => 'visit', 'data' => [
                    'heading' => 'Open from seven',
                    'intro' => 'Weekdays are counter service, and the queue moves quicker than it looks — order, find a chair, follow the coffee. Weekends, brunch takes bookings.',
                    'show_hours' => true,
                    'show_map' => true,
                    'note' => 'Bike racks out front; the corner table keeps the sun until eleven.',
                ]],
                ['type' => 'offerings', 'tone' => 'base', 'spacing' => 'airy', 'data' => [
                    'heading' => 'The daytime menu',
                    'intro' => 'Brunch runs until close, because mornings here refuse to end at eleven. The counter holds whatever came out of the oven last.',
                    'items' => [
                        ['group' => 'Brunch', 'name' => '{brunch_one}', 'price' => '{brunch_one_price}', 'description' => 'The plate the weekend queue is for. Three to a stack, brown at the edges, and the butter melts in on the way to the table.'],
                        ['group' => 'Brunch', 'name' => '{brunch_two}', 'price' => '{brunch_two_price}', 'description' => 'Eggs poached straight into the green pan, with sourdough grilled on the flat top for the wiping-up.'],
                        ['group' => 'Brunch', 'name' => 'Soft scrambled eggs, chives & thick toast', 'price' => '$12', 'description' => 'The quiet order. Cooked slower than you would at home, which is the entire trick.'],
                        ['group' => 'From the counter', 'name' => 'Cardamom morning bun', 'price' => '$6', 'description' => 'Laminated the afternoon before, baked at six, usually gone by ten. Regulars ask us to hold one with their coffee order.'],
                        ['group' => 'Coffee', 'name' => '{signature_coffee}', 'price' => '{signature_coffee_price}', 'description' => 'One origin at a time, changed when the roaster changes it, dialled in before the doors open.'],
                        ['group' => 'Coffee', 'name' => 'Batch filter, bottomless with breakfast', 'price' => '$4', 'description' => 'Brewed by the litre all morning. The right answer when the flat white queue is four deep.'],
                    ],
                ]],
                ['type' => 'prose', 'variant' => 'side-heading', 'tone' => 'muted', 'data' => [
                    'heading' => 'Set by the ovens',
                    'paragraphs' => [
                        ['text' => 'The day runs on the bake. Croissants are shaped the afternoon before and proved overnight, the first trays go in at five, and what you see in the case is everything there is — when the morning buns run out, they have run out until tomorrow.'],
                        ['text' => 'The coffee comes from a roaster four blocks away, collected on foot twice a week. We have bought from them since the week we opened, mostly because walking the beans home keeps both sides honest.'],
                    ],
                ]],
                ['type' => 'gallery', 'variant' => 'masonry', 'tone' => 'base', 'data' => [
                    'heading' => 'The room, mid-morning',
                    'images' => [
                        ['alt' => 'Sun across the corner table at nine'],
                        ['alt' => 'Hotcakes leaving the pass'],
                        ['alt' => 'The pastry case, freshly stocked'],
                        ['alt' => 'A flat white being poured at the counter'],
                    ],
                    'image_query' => 'sunlit cafe interior morning light',
                ]],
                ['type' => 'testimonials', 'variant' => 'grid', 'tone' => 'muted', 'data' => [
                    'heading' => 'From the neighbourhood',
                    'testimonials' => [
                        ['quote' => 'My Saturday run ends here. It started as a coincidence and is now, officially, the route.', 'author' => 'Nora F.', 'role' => 'Saturdays, table by the window'],
                        ['quote' => 'I came in for a coffee the week they opened and have somehow hosted every family birthday here since.', 'author' => 'Marcus T.', 'role' => 'Four blocks away'],
                    ],
                ]],
                ['type' => 'signup', 'variant' => 'banner', 'tone' => 'accent', 'data' => [
                    'heading' => 'The tenth coffee is ours',
                    'offer' => 'Leave an email and we will keep a card behind the counter with your name on it — nine stamps, then the tenth coffee is on the house, plus first word when the seasonal bakes change.',
                    'fields' => 'email',
                    'button_label' => 'Start my card',
                    'success_message' => 'Done — your card is behind the counter, first stamp waiting.',
                    'fine_print' => 'One email a month, mostly about pastry. Unsubscribe any time.',
                ]],
                ['type' => 'cta', 'variant' => 'banner', 'tone' => 'accent', 'data' => [
                    'heading' => 'Weekends book out by Thursday',
                    'body' => 'Saturday and Sunday brunch takes tables in advance. Weekdays, just walk in — the queue is part of the morning.',
                    'cta_label' => 'Book brunch',
                    'cta_url' => '/contact',
                    'secondary_label' => 'How the mornings run',
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
            'title' => 'The mornings',
            'meta_description' => 'How {business_name} bakes, brews and gets the doors open by seven.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'centered-minimal', 'data' => [
                    'eyebrow' => 'Ovens on at five',
                    'heading' => 'A café built around the morning',
                    'subheading' => 'Two ovens, one espresso machine, and a day that is over by three because all of it happened before noon.',
                ]],
                ['type' => 'prose', 'variant' => 'stacked', 'data' => [
                    'heading' => 'How it started',
                    'paragraphs' => [
                        ['text' => 'This was a weekend bread stall first — two folding tables outside the hardware store, sold out by nine most Saturdays. When the corner shop came up for lease, the stall moved indoors and brought its ovens with it.'],
                        ['text' => 'The bake still sets the schedule. Doughs are mixed and shaped the afternoon before, proved overnight in the cold room, and baked in one long run from five — croissants first, then the buns, then the loaves that flavour the toast.'],
                        ['text' => 'Brunch grew out of the bread, not the other way round. Once there was good sourdough every morning, eggs were the obvious next question, and the answer has been getting longer ever since.'],
                    ],
                ]],
                ['type' => 'steps', 'variant' => 'timeline', 'tone' => 'muted', 'data' => [
                    'heading' => 'The morning, in order',
                    'intro' => 'Seven days a week this runs the same way, and the whole café is downstream of it.',
                    'steps' => [
                        ['title' => 'Ovens on at five', 'description' => 'The bakers arrive in the dark, the ovens come up to heat, and the overnight proofs come out of the cold room.'],
                        ['title' => 'Bake', 'description' => 'Croissants first, morning buns behind them, loaves last. Two hours, one long run, no second batch.'],
                        ['title' => 'Doors at seven', 'description' => 'The first hour belongs to the regulars — the same orders, the good window light, the batch filter doing most of the work.'],
                        ['title' => 'Brunch', 'description' => 'The pans go on from eight and stay on until close. When the hotcake batter runs out, that is the day calling time.'],
                    ],
                ]],
                ['type' => 'gallery', 'variant' => 'grid', 'data' => [
                    'heading' => 'Before the doors open',
                    'images' => [
                        ['alt' => 'The first trays coming out at six'],
                        ['alt' => 'The case being stocked bun by bun'],
                        ['alt' => 'Chairs coming down off the tables at seven'],
                    ],
                    'image_query' => 'pastry counter bakery case',
                ]],
                ['type' => 'cta', 'variant' => 'banner', 'data' => [
                    'heading' => 'Come while it is warm',
                    'body' => 'The buns are best in the first hour and the light is best in the second. Either way, come in the morning.',
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
            'meta_description' => 'Hours, address and weekend brunch bookings for {business_name} in {city}.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'centered-minimal', 'data' => [
                    'eyebrow' => '{city}',
                    'heading' => 'Come by',
                    'subheading' => 'Walk in any weekday from seven. For weekend brunch, book a table below and skip the pavement wait.',
                ]],
                ['type' => 'reservation', 'tone' => 'base', 'spacing' => 'airy', 'data' => [
                    'heading' => 'Book a weekend table',
                    'intro' => 'Weekdays are walk-in, always. Saturday and Sunday brunch you can book — pick a morning and tell us how many chairs.',
                    'max_party_size' => 8,
                    'button_label' => 'Request a table',
                    'success_message' => 'Request received — we will confirm by email before the end of the day.',
                    'fine_print' => 'Bigger table? Call {phone} and we will push some together.',
                ]],
                ['type' => 'visit', 'tone' => 'muted', 'data' => [
                    'heading' => 'Finding us',
                    'intro' => 'The corner with the yellow awning. Street parking is patient on weekdays; weekends, the bus and the bike racks are the smarter bet.',
                    'show_hours' => true,
                    'show_map' => true,
                ]],
                ['type' => 'faq', 'tone' => 'base', 'data' => [
                    'heading' => 'Worth knowing first',
                    'questions' => [
                        ['question' => 'Can I bring my dog?', 'answer' => 'To the pavement tables, gladly — there is a water bowl by the door and the occasional heel of yesterday’s loaf for good sitters.'],
                        ['question' => 'Can I work here on a laptop?', 'answer' => 'Weekdays, yes — the wifi code is on your receipt and the back wall has the sockets. On weekends we ask for the screens away by ten, because brunch needs the tables more than the inbox does.'],
                        ['question' => 'Is anything gluten-free?', 'answer' => 'The granola always, most of the egg dishes on request, and a buckwheat hotcake that is nobody’s consolation prize. The bakes share ovens with wheat, so strict coeliacs should ask before choosing.'],
                        ['question' => 'Can I book a weekday table?', 'answer' => 'No — weekdays stay walk-in so a table is never sitting empty against a name. Come before half past eight or after eleven and you will have your pick.'],
                    ],
                ]],
            ],
        ];
    }
}
