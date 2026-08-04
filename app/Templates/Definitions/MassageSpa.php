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
 * Massage and day spa — the template that wins by leaving things out.
 *
 * Where {@see ChineseRestaurant} fills the first viewport with a photograph and
 * {@see DesignerPortfolio} puts work on black, this one is the quiet case: a
 * priced treatment menu, a single booking phone number, and a home page that
 * asks once. Wellness sites fail in a very specific way — banner offers,
 * membership tiers, three competing buttons above the fold — and a visitor
 * reads all of that as pressure, which is the opposite of the thing being sold.
 *
 * CalmCoastal, unmodified: Ocean blues, large soft corners, a spacious vertical
 * rhythm and refined type. It is the one preset with no dark band anywhere in
 * its repertoire, so there is no band to reach for and nothing to shout with.
 * That restraint is not a stylistic preference here, it is the entire pitch —
 * the tall centred hero and the single closing CTA are load-bearing, and a
 * second `cta` block on the home page would undo the argument the template is
 * making.
 */
final readonly class MassageSpa
{
    public static function definition(): TemplateDefinition
    {
        return new TemplateDefinition(
            preset: StylePreset::CalmCoastal,
            brandPrimary: '#3F7A8C',
            brandSecondary: '#2B3A42',
            brandAccent: '#A8C6C9',
            category: 'day spa',
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
            name: 'Still Harbor Massage & Day Spa',
            tagline: 'Unhurried bodywork, two blocks from the water',
            description: 'A four-room day spa booking therapeutic and relaxation massage in long sessions, with a buffer either side so nobody is moved along.',
            city: 'Bellingham',
            phone: '(360) 555-0148',
            email: 'frontdesk@stillharbor.example',
            addressLine1: '1140 Harris Avenue',
            state: 'WA',
            postalCode: '98225',
        );
    }

    /**
     * @return list<TemplateField>
     */
    private static function extraFields(): array
    {
        return [
            new TemplateField('treatment_one', 'Deep Tissue Massage'),
            new TemplateField('treatment_one_price', '90 minutes · $145'),
            new TemplateField('treatment_two', 'Prenatal Massage'),
            new TemplateField('treatment_two_price', '60 minutes · $115'),
            new TemplateField('treatment_three', 'Hot Stone Massage'),
            new TemplateField('treatment_three_price', '75 minutes · $130'),
        ];
    }

    /**
     * @return list<PhotoQuery>
     */
    private static function photoQueries(): array
    {
        return [
            new PhotoQuery('spa treatment room massage table', category: PhotoCategory::Interior, count: 4),
            new PhotoQuery('calm spa interior soft light', category: PhotoCategory::Interior, count: 4),
            new PhotoQuery('massage therapy hands shoulders', category: PhotoCategory::People, count: 4),
            new PhotoQuery('smooth stones water wellness detail', category: PhotoCategory::Nature, count: 3),
            new PhotoQuery('massage therapist portrait calm', PhotoOrientation::Portrait, PhotoCategory::People, 3),
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
                    ['label' => 'Treatments', 'url' => '/services'],
                    ['label' => 'Visit', 'url' => '/contact'],
                ],
                'cta_label' => 'Book a session',
                'cta_url' => 'tel:{phone}',
            ]],
            ['type' => 'footer', 'data' => [
                'variant' => 'soft',
                'nav_links' => [
                    ['label' => 'Treatments', 'url' => '/services'],
                    ['label' => 'Visit', 'url' => '/contact'],
                ],
                'note' => '{business_name} — {city}. Tuesday to Sunday, by appointment.',
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
            'meta_description' => '{business_name} in {city} — {tagline}. Massage booked in long sessions, priced by the hour.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'centered-minimal', 'spacing' => 'tall', 'data' => [
                    'eyebrow' => '{city}',
                    'heading' => '{tagline}',
                    'subheading' => 'Four treatment rooms, a full hour at minimum, and a front desk that will not sell you a package on the way out.',
                    'cta_label' => 'Book a session',
                    'cta_url' => 'tel:{phone}',
                    'image_query' => 'calm spa interior soft light',
                ]],
                ['type' => 'offerings', 'data' => [
                    'heading' => 'The treatment menu',
                    'intro' => 'The three we book most. Prices are per session, and the time listed is time on the table.',
                    'items' => [
                        ['group' => 'Massage', 'name' => '{treatment_one}', 'price' => '{treatment_one_price}', 'description' => 'Slow, firm work through the shoulders, neck and lower back. You will be asked how the pressure feels, and the answer changes what happens next.'],
                        ['group' => 'Massage', 'name' => '{treatment_two}', 'price' => '{treatment_two_price}', 'description' => 'Side-lying and properly bolstered, with a therapist who has done a few hundred of these. From the second trimester onward.'],
                        ['group' => 'Massage', 'name' => '{treatment_three}', 'price' => '{treatment_three_price}', 'description' => 'Basalt stones held at one temperature the whole way through, so the heat lasts as long as the session does.'],
                    ],
                ]],
                // Placed on the price, where the decision actually happens.
                // A first-visit offer is the standard lever for a treatment
                // room: the second booking is where the money is, and the
                // first one is what people hesitate over.
                ['type' => 'signup', 'variant' => 'banner', 'tone' => 'accent', 'data' => [
                    'heading' => '£10 off your first treatment',
                    'offer' => 'Leave an email and we will send the code, plus the quiet weekday slots before they go.',
                    'fields' => 'email',
                    'button_label' => 'Send my code',
                    'success_message' => 'Sent — check your inbox for the code.',
                    'fine_print' => 'One email a month at most. Unsubscribe any time.',
                ]],
                ['type' => 'steps', 'variant' => 'timeline', 'data' => [
                    'heading' => 'What a first visit looks like',
                    'intro' => 'No membership, no consultation fee, no forms you have not seen before.',
                    'steps' => [
                        ['title' => 'Arrive ten minutes early', 'description' => 'Long enough for tea and a short intake form about anything that hurts. There is no hurry built into the schedule.'],
                        ['title' => 'Say what you want from the hour', 'description' => 'Pressure, the areas to work on, whether you would rather not talk. All of it is fair to say out loud, and none of it is a special request.'],
                        ['title' => 'The room is yours for the whole session', 'description' => 'Appointments are booked with a gap either side, so nobody knocks and nothing gets trimmed at the end.'],
                        ['title' => 'Leave when you are ready', 'description' => 'Water, a quiet chair in the hallway, and the front desk only when you come to it.'],
                    ],
                ]],
                ['type' => 'prose', 'variant' => 'stacked', 'data' => [
                    'heading' => 'Why the short massage is not on the menu',
                    'paragraphs' => [
                        ['text' => 'A fifty-minute massage spends its first fifteen finding the problem. By the time the work is actually doing something, someone is telling you to sit up slowly.'],
                        ['text' => 'So the shortest thing we book is an hour, and most people book ninety minutes. It costs more than the other thing and it is worth more than the other thing, and we would rather say that plainly than hide it inside a membership.'],
                    ],
                ]],
                ['type' => 'gallery', 'variant' => 'grid', 'data' => [
                    'heading' => 'The rooms',
                    'images' => [
                        ['alt' => 'A treatment room before the first appointment of the day'],
                        ['alt' => 'The quiet room off the hallway'],
                        ['alt' => 'Warm towels and basalt stones on the counter'],
                        ['alt' => 'The front desk in morning light'],
                    ],
                    'image_query' => 'spa treatment room massage table',
                ]],
                ['type' => 'testimonials', 'variant' => 'grid', 'data' => [
                    'heading' => 'From people who come back',
                    'testimonials' => [
                        ['quote' => 'Four years of a bad shoulder and two physios. Nadia found the spot in about ten minutes and then spent the rest of the hour on it.', 'author' => 'Priya R.', 'role' => 'Books monthly'],
                        ['quote' => 'What I actually noticed was that nobody tried to sell me anything at the desk afterwards. I have been back six times since.', 'author' => 'Tom Vasquez', 'role' => 'Regular since spring'],
                    ],
                ]],
                ['type' => 'cta', 'variant' => 'banner', 'data' => [
                    'heading' => 'There are rooms open this week',
                    'body' => 'Same-day appointments are usually possible before noon. Call the desk and we will find you an hour.',
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
            'title' => 'Treatments',
            'meta_description' => 'Massage, bodywork and add-ons at {business_name} in {city} — how long each session runs and what it costs.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'centered-minimal', 'data' => [
                    'eyebrow' => 'Treatments',
                    'heading' => 'The whole menu, with the times and the prices on it',
                    'subheading' => 'No tiers, no membership, nothing that costs more on a Saturday. You book an hour, you get an hour.',
                ]],
                ['type' => 'offerings', 'data' => [
                    'heading' => 'Massage and bodywork',
                    'intro' => 'Every price is the price. Add-ons go onto an appointment you have already booked — they are never offered at the door.',
                    'items' => [
                        ['group' => 'Massage', 'name' => '{treatment_one}', 'price' => '{treatment_one_price}', 'description' => 'For a specific complaint: a shoulder that has stopped rotating properly, a lower back that objects to sitting. Expect to be sore the next morning and better by the evening.'],
                        ['group' => 'Massage', 'name' => '{treatment_two}', 'price' => '{treatment_two_price}', 'description' => 'Booked on the side with a full bolster set, and shortened at any point without losing the rest of the hour. Bring anything your midwife has asked us to avoid.'],
                        ['group' => 'Massage', 'name' => '{treatment_three}', 'price' => '{treatment_three_price}', 'description' => 'Heat first, hands second. Good in February, good for anyone who finds deep work more than they want.'],
                        ['group' => 'Body and face', 'name' => 'Sea Salt Body Scrub', 'price' => '45 minutes · $95', 'description' => 'Salt, oil, a warm shower and nothing else. Often booked in front of a massage rather than instead of one.'],
                        ['group' => 'Body and face', 'name' => 'The Quiet Facial', 'price' => '60 minutes · $120', 'description' => 'Cleanse, steam, extraction only where it is warranted, and no running commentary on your skin.'],
                        ['group' => 'Add-ons', 'name' => 'Scalp and neck extension', 'price' => '15 minutes · $30', 'description' => 'Fifteen more minutes at the top of the table. The most-requested thing on this page.'],
                        ['group' => 'Add-ons', 'name' => 'Arnica for worked areas', 'price' => '$12', 'description' => 'Applied at the end of a deep session. Worth it if tomorrow involves lifting anything.'],
                    ],
                ]],
                ['type' => 'features', 'variant' => 'icon-rows', 'data' => [
                    'heading' => 'What every session includes',
                    'intro' => 'The same four things whether you booked the cheapest hour or the longest one.',
                    'features' => [
                        ['icon' => '01', 'title' => 'A therapist who read the form', 'description' => 'Your intake notes go to the room before you do, so the first five minutes are not spent repeating an injury you already wrote down.'],
                        ['icon' => '02', 'title' => 'A room with a buffer either side', 'description' => 'We book fewer appointments a day than we could. It is why sessions start on time and end when they are meant to.'],
                        ['icon' => '03', 'title' => 'Pressure checked, not assumed', 'description' => 'Firm means different things to different backs. You will be asked early, and you can change your mind halfway.'],
                        ['icon' => '04', 'title' => 'One price, said out loud', 'description' => 'What you see here is what the desk charges. Gratuity is welcome and genuinely optional.'],
                    ],
                ]],
                ['type' => 'faq', 'data' => [
                    'heading' => 'Booking questions',
                    'intro' => 'The four we answer on the phone most days.',
                    'questions' => [
                        ['question' => 'How far ahead should I book?', 'answer' => 'A week for evenings and weekends, a day or two for weekday mornings. Cancellations open up constantly — it is always worth calling {phone}.'],
                        ['question' => 'Can I request a particular therapist?', 'answer' => 'Yes, and most regulars do. Tell the desk when you book and we will work around their schedule rather than yours.'],
                        ['question' => 'Do you provide receipts for insurance or an HSA?', 'answer' => 'We do, itemised, emailed the same day. We are not able to bill an insurer directly.'],
                        ['question' => 'Are gift cards available?', 'answer' => 'In any amount, at the desk or over the phone. They do not expire and they are not restricted to a single treatment.'],
                    ],
                ]],
                ['type' => 'cta', 'variant' => 'banner', 'data' => [
                    'heading' => 'Ready when you are',
                    'body' => 'Tell us what hurts, or tell us you just want an hour of quiet. Both are a good reason to call.',
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
                    'subheading' => 'Two blocks up from the water, above the bakery. Ours is the blue door.',
                ]],
                ['type' => 'contact', 'data' => [
                    'heading' => 'Book, or ask first',
                    'intro' => 'The desk answers between appointments. If it rings out, leave a message or send this form and you will hear back the same day.',
                    'show_form' => true,
                    'success_message' => 'Thank you — we will be in touch today.',
                ]],
                ['type' => 'faq', 'data' => [
                    'heading' => 'Before you arrive',
                    'questions' => [
                        ['question' => 'How early should I get there?', 'answer' => 'Ten minutes for a first visit, five after that. Arriving late shortens the massage rather than the hour we set aside, so it is worth the head start.'],
                        ['question' => 'Where do I park?', 'answer' => 'Free two-hour parking on Harris Avenue and a small lot behind the building. Both fill up around lunchtime.'],
                        ['question' => 'What if I need to cancel?', 'answer' => 'Call {phone} at least twenty-four hours ahead and there is no charge at all. Inside that we ask for half, because the room stays empty.'],
                        ['question' => 'Is the spa accessible?', 'answer' => 'There is a lift to the first floor and two of the four rooms have height-adjustable tables. Mention it when you book and we will assign one of those.'],
                    ],
                ]],
            ],
        ];
    }
}
