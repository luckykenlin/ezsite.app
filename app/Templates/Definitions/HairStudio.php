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
 * An independent hair studio — the template where the typography is the design.
 *
 * QuietLuxe, unmodified, and the only template in the library that opens on the
 * `full-viewport-quiet` hero: a screenful of warm stone, a hairline garamond set
 * enormous, and one outlined button. There is deliberately no photograph in the
 * first viewport. A hair studio's stock imagery is the least distinctive asset
 * it has — every salon on the street can buy the same picture of the same
 * shampoo bowl — so leading with a photograph makes a premium studio look like
 * every other one, while leading with words makes a claim only this business
 * gets to make. The photographs are still here; they are just below the fold,
 * where they answer a question the visitor has by then actually asked.
 *
 * It is the third salon-adjacent template and shares nothing visually with the
 * other two, which is the point of having a preset library at all:
 * {@see NailSalon} is a dark room with gold on it, {@see MassageSpa} is airy
 * ocean blues, and this one is monochrome stone with square corners. Same
 * trade-adjacent market, three different arguments.
 *
 * It is also the only template whose sections MOVE — QuietLuxe is the one preset
 * that asks for {@see \App\Design\MotionStyle::Reveal} — and the restraint
 * elsewhere is what pays for it. On a page with a coloured accent band and three
 * competing buttons, sections fading in reads as a template; on this one it is
 * the only thing that happens.
 */
final readonly class HairStudio
{
    public static function definition(): TemplateDefinition
    {
        return new TemplateDefinition(
            preset: StylePreset::QuietLuxe,
            brandPrimary: '#1C1917',
            brandSecondary: '#78716C',
            brandAccent: '#D6D3D1',
            category: 'hair salon',
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
            name: 'The Long Room',
            tagline: 'Unhurried haircuts, four chairs, one room',
            description: 'A four-chair hair studio in inner south-east Portland doing precision cutting, tonal colour and long appointments — one stylist with you the whole way through.',
            city: 'Portland',
            phone: '(503) 555-0173',
            email: 'hello@thelongroom.example',
            addressLine1: '2214 SE Clinton Street',
            state: 'OR',
            postalCode: '97202',
            timezone: 'America/Los_Angeles',
            openingHours: [
                'monday' => [],
                'tuesday' => ['10:00-19:00'],
                'wednesday' => ['10:00-19:00'],
                'thursday' => ['10:00-20:00'],
                'friday' => ['10:00-20:00'],
                'saturday' => ['09:00-17:00'],
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
            new TemplateField('service_one', 'Your most-booked service', 'Cut & Finish'),
            new TemplateField('service_one_price', 'How long it runs and what it costs', '75 minutes · $110', help: 'Length first, then price — it reads as one line on the menu.'),
            new TemplateField('service_two', 'A second service', 'Gloss & Tone'),
            new TemplateField('service_two_price', 'Its length and price', '45 minutes · $75'),
            new TemplateField('service_three', 'One more', 'Full Highlights'),
            new TemplateField('service_three_price', 'Its length and price', '3 hours · $265'),
        ];
    }

    /**
     * @return list<PhotoQuery>
     */
    private static function photoQueries(): array
    {
        return [
            new PhotoQuery('minimal hair salon interior daylight', category: PhotoCategory::Interior, count: 4),
            new PhotoQuery('hairdresser cutting hair in studio', category: PhotoCategory::People, count: 4),
            new PhotoQuery('hair texture detail close up', category: PhotoCategory::People, count: 3),
            new PhotoQuery('salon mirror chair warm light', category: PhotoCategory::Interior, count: 3),
            new PhotoQuery('hair stylist portrait calm', PhotoOrientation::Portrait, PhotoCategory::People, 3),
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
                    ['label' => 'Services', 'url' => '/services'],
                    ['label' => 'Visit', 'url' => '/contact'],
                ],
                'cta_label' => 'Book',
                'cta_url' => 'tel:{phone}',
            ]],
            ['type' => 'footer', 'data' => [
                'variant' => 'minimal',
                'nav_links' => [
                    ['label' => 'Services', 'url' => '/services'],
                    ['label' => 'Visit', 'url' => '/contact'],
                ],
                'note' => '{business_name} — {city}. Tuesday to Saturday, by appointment.',
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
            'meta_description' => '{business_name} — {tagline}. A four-chair hair studio in {city} doing precision cutting and tonal colour by appointment.',
            'blocks' => [
                // The one full-viewport hero in the library, and the one block on
                // this page with no photograph anywhere near it.
                // The heading is authored short rather than filled from
                // `{tagline}`: a headline set this large is a two-or-three word
                // instrument, and a tagline is whatever length its owner wrote.
                // The tagline still opens the sentence underneath it.
                ['type' => 'hero', 'variant' => 'full-viewport-quiet', 'data' => [
                    'eyebrow' => 'Est. 2019 · {city}',
                    'heading' => 'Unhurried by design',
                    'subheading' => '{tagline}. One stylist, one chair, the whole appointment.',
                    'cta_label' => 'Book a chair',
                    'cta_url' => 'tel:{phone}',
                ]],
                ['type' => 'prose', 'variant' => 'stacked', 'data' => [
                    'heading' => 'Why the haircut takes an hour and a quarter',
                    'paragraphs' => [
                        ['text' => 'Most of a good haircut is the twenty minutes before anyone picks up scissors: how your hair actually falls, what it did the last time somebody cut it short, and what you are willing to do to it on a Tuesday morning.'],
                        ['text' => 'That conversation does not fit inside a forty-minute slot, so we do not sell one. Every appointment here is long enough to include it, which is also why we are not the cheapest room on this street and why people come back for years.'],
                    ],
                ]],
                ['type' => 'offerings', 'data' => [
                    'heading' => 'The menu',
                    'intro' => 'The three we book most. Colour is quoted at the consultation — the number below is where it starts, not an average.',
                    'items' => [
                        ['group' => 'Cutting', 'name' => '{service_one}', 'price' => '{service_one_price}', 'description' => 'A consultation, a wash, the cut itself and a rough dry so you leave knowing what it will do at home. Fringe trims between appointments are free.'],
                        ['group' => 'Colour', 'name' => '{service_two}', 'price' => '{service_two_price}', 'description' => 'A tone shift rather than a colour change: brass taken out, depth put back, no line to grow out. Often booked alongside a cut.'],
                        ['group' => 'Colour', 'name' => '{service_three}', 'price' => '{service_three_price}', 'description' => 'Hand-painted, foil-free through the mid-lengths, finished with a gloss. Long appointment, and we will tell you honestly if your hair should wait.'],
                    ],
                ]],
                ['type' => 'gallery', 'variant' => 'grid', 'data' => [
                    'heading' => 'Recent work',
                    'images' => [
                        ['alt' => 'A grown-out bob cut back to a hard line'],
                        ['alt' => 'Tonal colour on dark hair, finished with a gloss'],
                        ['alt' => 'A short crop from the back'],
                        ['alt' => 'Hand-painted highlights through the mid-lengths'],
                    ],
                    'image_query' => 'hair texture detail close up',
                ]],
                ['type' => 'testimonials', 'variant' => 'spotlight', 'data' => [
                    'heading' => 'From the chair',
                    'testimonials' => [
                        ['quote' => 'I asked for the same thing I have asked five other salons for and this is the first time anyone told me it would not work on my hair. Then she cut something better.', 'author' => 'Renata M.', 'role' => 'Books every ten weeks'],
                        ['quote' => 'Nobody upsold me anything. I paid for a cut, I got an hour and a quarter, and I have stopped shopping around.', 'author' => 'Dan Osei', 'role' => 'Regular since 2021'],
                    ],
                ]],
                // The quiet signup, low on the page and stacked rather than a
                // banner: this template's whole argument is that nothing shouts,
                // and a full-width offer band two screens after the hero would
                // be the one thing on the site that does.
                ['type' => 'signup', 'variant' => 'stacked', 'data' => [
                    'heading' => 'Cancellations, before they go public',
                    'offer' => 'We hold a short list for last-minute openings. Leave an email and you will hear about the Tuesday and Thursday gaps first.',
                    'fields' => 'email',
                    'button_label' => 'Add me to the list',
                    'success_message' => 'Done — you are on the list.',
                    'fine_print' => 'One email a month at most, and only when there is something to say.',
                ]],
                ['type' => 'faq', 'data' => [
                    'heading' => 'Before you book',
                    'questions' => [
                        ['question' => 'Do I need a consultation first?', 'answer' => 'For a cut, no — the consultation is the first part of the appointment. For a colour change on hair that has been coloured before, yes, and it is free: fifteen minutes, no obligation, and we can test a strand while you are here.'],
                        ['question' => 'How far ahead should I book?', 'answer' => 'Two to three weeks for evenings and Saturdays, a few days for weekday mornings. Call {phone} — cancellations open up constantly and are not always online yet.'],
                        ['question' => 'Can I book with a specific stylist?', 'answer' => 'Yes, and most people do. Say who when you book and we will work around their week rather than yours.'],
                    ],
                ]],
                ['type' => 'cta', 'variant' => 'banner', 'data' => [
                    'heading' => 'There are chairs open this week',
                    'body' => 'The desk answers between appointments. Tell us what your hair is doing and we will tell you how long it needs.',
                    'cta_label' => 'Call {phone}',
                    'cta_url' => 'tel:{phone}',
                    'secondary_label' => 'See the full menu',
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
            'title' => 'Services',
            'meta_description' => 'Cutting, colour and finishing at {business_name} in {city} — how long each appointment runs and what it costs.',
            'blocks' => [
                // Inner pages step down to the centred hero on purpose: the
                // full-viewport opening is an argument, and an argument made
                // three times in one visit stops being one.
                ['type' => 'hero', 'variant' => 'centered-minimal', 'data' => [
                    'eyebrow' => 'Services',
                    'heading' => 'The whole menu, with the times on it',
                    'subheading' => 'Every price includes the consultation and the finish. Nothing here costs more on a Saturday.',
                ]],
                ['type' => 'offerings', 'data' => [
                    'heading' => 'Cutting, colour and finishing',
                    'intro' => 'Colour prices start where they are listed. Long or dense hair takes more product and more time, and you will be told which before anything is mixed — never at the till.',
                    'items' => [
                        ['group' => 'Cutting', 'name' => '{service_one}', 'price' => '{service_one_price}', 'description' => 'The standard appointment: consultation, wash, cut, rough dry. Includes free fringe trims for as long as the cut lasts.'],
                        ['group' => 'Cutting', 'name' => 'Restyle', 'price' => '105 minutes · $145', 'description' => 'For a real change of shape — long to short, a fringe you have never had, growing out something you regret. Booked longer because the first twenty minutes decide the rest.'],
                        ['group' => 'Cutting', 'name' => 'Clipper & Scissor Cut', 'price' => '45 minutes · $65', 'description' => 'Short hair kept sharp, every three to four weeks. The one appointment here that is genuinely quick.'],
                        ['group' => 'Colour', 'name' => '{service_two}', 'price' => '{service_two_price}', 'description' => 'Tone only. Takes brass out, puts depth back, and leaves no regrowth line to manage.'],
                        ['group' => 'Colour', 'name' => '{service_three}', 'price' => '{service_three_price}', 'description' => 'Hand-painted through the mid-lengths and finished with a gloss. A long sit; bring something to read.'],
                        ['group' => 'Colour', 'name' => 'Single Process Root', 'price' => '90 minutes · $115', 'description' => 'Regrowth matched to what is already there. Booked with a cut by most people, and cheaper that way than separately.'],
                        ['group' => 'Finishing', 'name' => 'Blow Dry & Style', 'price' => '40 minutes · $55', 'description' => 'On its own, or before something. No set, no lacquer unless you ask.'],
                    ],
                ]],
                ['type' => 'features', 'variant' => 'icon-rows', 'data' => [
                    'heading' => 'What every appointment includes',
                    'intro' => 'The same four things whether you booked the shortest slot or the longest one.',
                    'features' => [
                        ['icon' => '01', 'title' => 'A consultation, not a checkbox', 'description' => 'Dry hair, good light, and a conversation about what it does on the days you are not here. It happens before the wash, because after the wash it is too late to change the plan.'],
                        ['icon' => '02', 'title' => 'One stylist, start to finish', 'description' => 'Nobody hands you off halfway. The person who agreed the shape with you is the person who cuts it and the person who dries it.'],
                        ['icon' => '03', 'title' => 'The price you were quoted', 'description' => 'Colour is quoted before it is mixed. If your hair needs more than the quote allowed for, you hear it at the mirror, not at the till.'],
                        ['icon' => '04', 'title' => 'Nothing sold at the door', 'description' => 'We will tell you what we used if you ask. We will not put three bottles on the counter while you are getting your coat.'],
                    ],
                ]],
                ['type' => 'faq', 'data' => [
                    'heading' => 'Booking questions',
                    'intro' => 'The four we answer on the phone most days.',
                    'questions' => [
                        ['question' => 'Do you do box-dye corrections?', 'answer' => 'Often, and honestly: sometimes it is one appointment and sometimes it is three across two months. The free consultation exists so that number comes from looking at your hair rather than from guessing.'],
                        ['question' => 'What if I do not like it?', 'answer' => 'Say so in the chair — that is what the mirror at the end is for, and adjustments are free. Come back inside two weeks and we will still put it right at no charge.'],
                        ['question' => 'Do you have gift vouchers?', 'answer' => 'For any amount, at the desk or over the phone. They do not expire and they are not tied to one service.'],
                        ['question' => 'Is there a cancellation charge?', 'answer' => 'Not with twenty-four hours notice. Inside that we ask for half, because a three-hour colour slot cannot be refilled the same morning.'],
                    ],
                ]],
                ['type' => 'cta', 'variant' => 'banner', 'data' => [
                    'heading' => 'Book the appointment your hair needs',
                    'body' => 'Not sure which of these it is? Call and describe it — that is a faster conversation than reading this page twice.',
                    'cta_label' => 'Call {phone}',
                    'cta_url' => 'tel:{phone}',
                    'secondary_label' => 'Where to find us',
                    'secondary_url' => '/contact',
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
            'meta_description' => 'Address, parking, hours and booking for {business_name} in {city}.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'centered-minimal', 'data' => [
                    'eyebrow' => '{city}',
                    'heading' => 'Come and find us',
                    'subheading' => 'Ground floor, between the bike shop and the bakery. The window with nothing in it.',
                ]],
                ['type' => 'contact', 'data' => [
                    'heading' => 'Book, or ask first',
                    'intro' => 'The phone is answered between appointments. If it rings out, leave a message or send this and you will hear back the same day.',
                    'show_form' => true,
                    'success_message' => 'Thank you — we will be in touch today.',
                ]],
                ['type' => 'faq', 'data' => [
                    'heading' => 'Getting here',
                    'questions' => [
                        ['question' => 'Where do I park?', 'answer' => 'Free two-hour parking on Clinton and the side streets either way. It fills up around lunchtime, so leave a few extra minutes for a long appointment.'],
                        ['question' => 'How early should I arrive?', 'answer' => 'Five minutes is plenty. Arriving late shortens the haircut rather than the slot, so it is worth the head start on a colour day.'],
                        ['question' => 'Is the studio accessible?', 'answer' => 'Step-free from the street, and two of the four chairs have room for a wheelchair to come alongside. Mention it when you book and we will keep one of those.'],
                    ],
                ]],
            ],
        ];
    }
}
