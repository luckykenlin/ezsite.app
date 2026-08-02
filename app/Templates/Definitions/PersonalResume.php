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
 * Personal resume — the one template that is a person, not a premises.
 *
 * Every other definition in the library sells a business: a menu, a studio, a
 * front door. This one sells one person's judgement, which changes what the
 * page is allowed to do. There is no shopfront to photograph, no prices, and
 * nothing to browse — just a name, a claim, and the evidence for it in the
 * order an employer reads it. A Location row is still created (the profile
 * carries a full address), but the address is a detail on a contact page rather
 * than the reason anyone came.
 *
 * ProfessionalMinimal, unmodified: charcoal, modern sans, small radii, quiet
 * type, no dividers, and an appearance table that puts almost every section on
 * the page background. The restraint is not an absence of design — it is the
 * argument. Somebody being hired for their taste cannot afford a page that is
 * louder than they are, and a gradient behind a job title reads as a candidate
 * compensating.
 *
 * The one genuinely unusual choice: a TYPE-ONLY hero. `centered-minimal` with
 * no `image_query` at all, so this is the only template in the library where
 * nothing above the fold is a photograph. A stock picture of a stranger's desk
 * under a real person's name is worse than white space, and the name set large
 * on an empty field is the most confident thing this page can say.
 */
final readonly class PersonalResume
{
    public static function definition(): TemplateDefinition
    {
        return new TemplateDefinition(
            preset: StylePreset::ProfessionalMinimal,
            brandPrimary: '#22262B',
            brandSecondary: '#585F67',
            brandAccent: '#4A5D73',
            category: 'personal portfolio',
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
            name: 'Mara Whitfield',
            tagline: 'Staff engineer, data platforms',
            description: 'An engineer who builds the unglamorous infrastructure other teams depend on, and writes down how it works so nobody has to ask.',
            city: 'Chicago',
            phone: '(312) 555-0164',
            email: 'mara@whitfield.example',
            addressLine1: '1420 West Grand Avenue',
            state: 'IL',
            postalCode: '60642',
            timezone: 'America/Chicago',
        );
    }

    /**
     * @return list<TemplateField>
     */
    private static function extraFields(): array
    {
        return [
            new TemplateField('current_title', 'Your title right now', 'Staff Engineer, Northwind Data', help: 'One line. It sits directly under your name in the hero.'),
            new TemplateField('role_one', 'Your current or most recent role', 'Staff Engineer, Northwind Data (2021–now)'),
            new TemplateField('role_two', 'The one before it', 'Senior Engineer, Cartogram (2017–2021)'),
            new TemplateField('role_three', 'And the one before that', 'Software Engineer, Bellweather Health (2014–2017)'),
            new TemplateField('focus', 'The work you want more of', 'Data platforms other teams can build on without asking me first'),
        ];
    }

    /**
     * @return list<PhotoQuery>
     */
    private static function photoQueries(): array
    {
        return [
            new PhotoQuery('professional headshot neutral background', PhotoOrientation::Portrait, PhotoCategory::People, 4),
            new PhotoQuery('minimal desk workspace laptop notebook', category: PhotoCategory::Workspace, count: 4),
            new PhotoQuery('conference speaker on stage', category: PhotoCategory::People, count: 3),
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
                    ['label' => 'About', 'url' => '/about'],
                    ['label' => 'Contact', 'url' => '/contact'],
                ],
                'cta_label' => 'Email me',
                'cta_url' => 'mailto:{email}',
            ]],
            ['type' => 'footer', 'data' => [
                'variant' => 'minimal',
                'nav_links' => [
                    ['label' => 'About', 'url' => '/about'],
                    ['label' => 'Contact', 'url' => '/contact'],
                ],
                'note' => '{business_name} — {city}. Happy to talk, slow to answer on weekends.',
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
            'meta_description' => '{business_name} — {tagline}, based in {city}. Recent roles, what I work on, and how to reach me.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'centered-minimal', 'spacing' => 'tall', 'data' => [
                    'eyebrow' => '{city}',
                    'heading' => '{business_name}',
                    'subheading' => '{current_title}. I build the parts of a system nobody notices until they break, and try to leave them better documented than I found them.',
                    'cta_label' => 'Get in touch',
                    'cta_url' => '/contact',
                ]],
                ['type' => 'steps', 'variant' => 'timeline', 'data' => [
                    'heading' => 'Where I have worked',
                    'intro' => 'Most recent first. The short version — ask me about any of it.',
                    'steps' => [
                        ['title' => '{role_one}', 'description' => 'Own the data platform three product teams ship on. Cut the median pipeline failure from a next-morning problem to a self-healing one, and wrote the runbooks that made that possible without me.'],
                        ['title' => '{role_two}', 'description' => 'Joined as the fourth engineer and stayed through the part where everything needed rebuilding. Led the move off a single Postgres box without a maintenance window.'],
                        ['title' => '{role_three}', 'description' => 'Healthcare data, which teaches you quickly that correctness is not a preference. Learned to write software that fails loudly and on purpose.'],
                    ],
                ]],
                ['type' => 'prose', 'variant' => 'stacked', 'data' => [
                    'heading' => 'What I am like to work with',
                    'paragraphs' => [
                        ['text' => 'I am the person who asks what happens when this fails, usually before anyone wants to hear it. It is not pessimism — it is cheaper to answer that question in a design review than at two in the morning.'],
                        ['text' => 'Beyond that: I write things down, I prefer the boring solution, and I would rather ship something small this week than something complete next quarter.'],
                    ],
                ]],
                ['type' => 'features', 'variant' => 'icon-rows', 'tone' => 'muted', 'data' => [
                    'heading' => 'What I am useful for',
                    'intro' => 'Three things I have done enough times to be genuinely quick at.',
                    'features' => [
                        ['icon' => '01', 'title' => 'Systems that survive being popular', 'description' => 'Taking something that works for one team and making it work for ten, without a rewrite and without a heroics-based on-call rotation.'],
                        ['icon' => '02', 'title' => 'Untangling what already exists', 'description' => 'Reading an unfamiliar codebase, finding where the real problem lives, and proposing the smallest change that fixes it.'],
                        ['icon' => '03', 'title' => 'Being the person who writes it down', 'description' => 'Design docs, runbooks and postmortems that people actually read, because they are short and they answer the question that was asked.'],
                    ],
                ]],
                ['type' => 'cta', 'variant' => 'banner', 'data' => [
                    'heading' => 'Looking for someone like me?',
                    'body' => '{focus} — that is the work I am best at and the work I want more of. If it sounds like yours, send me a paragraph about it.',
                    'cta_label' => 'Email {email}',
                    'cta_url' => 'mailto:{email}',
                    'secondary_label' => 'The longer version',
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
            'title' => 'About',
            'meta_description' => 'How {business_name} ended up doing this work, and what a decade of it has taught.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'centered-minimal', 'data' => [
                    'eyebrow' => 'About',
                    'heading' => 'The longer version',
                    'subheading' => 'A résumé tells you where someone was. This is roughly why.',
                ]],
                ['type' => 'prose', 'variant' => 'stacked', 'data' => [
                    'heading' => 'How I got here',
                    'paragraphs' => [
                        ['text' => "I started in healthcare software, where a bug is not a bad quarter but a wrong number on somebody's chart. That first job set the standard for everything after it: know what your system does when it is wrong, and never let that be a surprise."],
                        ['text' => 'The years since have mostly been infrastructure — the layer other engineers build on and complain about, in that order. I have come to like it. The work is invisible when it goes well, which is a strange thing to be proud of, and I am.'],
                        ['text' => 'These days I spend as much time on the human side as the technical one: reviewing designs, sitting with the team that has to operate the thing, and arguing for the smaller version. Most of what I know now was learned by shipping the larger one first.'],
                    ],
                ]],
                ['type' => 'stats', 'data' => [
                    'heading' => 'For the skim-readers',
                    'stats' => [
                        ['value' => '11', 'label' => 'Years writing software professionally'],
                        ['value' => '3', 'label' => 'Companies, none of them briefly'],
                        ['value' => '2', 'label' => 'Platform migrations with no downtime'],
                    ],
                ]],
                ['type' => 'gallery', 'variant' => 'filmstrip', 'data' => [
                    'heading' => 'Talks and workshops',
                    'images' => [
                        ['alt' => 'Speaking at a regional engineering conference'],
                        ['alt' => 'A workshop on incident review'],
                        ['alt' => 'Q&A after a platform talk'],
                    ],
                    'image_query' => 'conference speaker on stage',
                ]],
                ['type' => 'gallery', 'variant' => 'grid', 'tone' => 'muted', 'data' => [
                    'heading' => 'Where the work happens',
                    'images' => [
                        ['alt' => 'A desk with a laptop and a notebook'],
                        ['alt' => 'Notes from a design review'],
                        ['alt' => 'Morning, before anything has broken'],
                    ],
                    'image_query' => 'minimal desk workspace laptop notebook',
                ]],
                ['type' => 'cta', 'variant' => 'banner', 'data' => [
                    'heading' => 'That is most of it',
                    'body' => 'If you want the parts that do not fit on a page, the fastest route is a short email.',
                    'cta_label' => 'Get in touch',
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
            'title' => 'Contact',
            'meta_description' => 'How to reach {business_name} in {city} — email, phone, and what to expect.',
            'blocks' => [
                ['type' => 'hero', 'variant' => 'centered-minimal', 'data' => [
                    'eyebrow' => '{city}',
                    'heading' => 'Say hello',
                    'subheading' => 'Email is best. A paragraph about the problem is more useful to me than a job title.',
                ]],
                ['type' => 'contact', 'data' => [
                    'heading' => 'Get in touch',
                    'intro' => 'Write to {email} or use the form — both land in the same place. I read everything and reply to anything specific.',
                    'show_form' => true,
                    'success_message' => 'Thank you — that came through. I will get back to you within a couple of days.',
                ]],
                ['type' => 'faq', 'tone' => 'muted', 'data' => [
                    'heading' => 'The questions I usually get',
                    'intro' => 'Answered here so neither of us has to spend an email on them.',
                    'questions' => [
                        ['question' => 'Are you open to new roles?', 'answer' => 'I am always willing to have the conversation, even when the answer ends up being no. {focus} is the thing most likely to get a yes.'],
                        ['question' => 'Do you work remotely?', 'answer' => 'Yes, and I have done it well for years. I am in {city} and happy to be in a room a few days a month if that matters to the team.'],
                        ['question' => 'Can you take on freelance work?', 'answer' => 'Occasionally, for scoped pieces — a review, a migration plan, a second opinion on an architecture. Call {phone} if it is time-sensitive.'],
                        ['question' => 'Can I see code?', 'answer' => 'Most of my recent work is not public, but I am happy to walk through the shape of it, and to talk through a problem of yours instead.'],
                    ],
                ]],
            ],
        ];
    }
}
