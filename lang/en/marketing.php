<?php

declare(strict_types=1);

/*
 * Every word on the CENTRAL marketing site: the frame, the four pages, the
 * apply wizard, the template library's prose and the style presets as the
 * marketing site describes them.
 *
 * ONE file, and the group name matters. Split into `central.php`,
 * `templates.php` and `design.php` it broke the tenant page editor:
 * FilamentServiceProvider calls `->translateLabel()` on every action, and
 * `__('Design')` resolves the group `Design` — which on a case-insensitive
 * filesystem finds `design.php` and hands Filament an ARRAY where it wants a
 * label. Green on Linux CI, fatal on a Mac. A group nobody would ever use as a
 * UI label removes the whole class of collision.
 *
 * Only the marketing site. `config('app.locale')` stays `en`, so nothing here
 * reaches a tenant site or either Filament panel — see App\Enums\Locale for why
 * that boundary is drawn where it is.
 *
 * Punctuation is written as real Unicode (’ — …) rather than HTML entities:
 * these strings are echoed through `{{ }}`, and an entity would arrive on the
 * page double-escaped.
 */

return [
    /*
     * Shared button labels. One key per action rather than one per slot: the
     * same "Browse the templates" sits in the hero, the footer and the closing
     * call to action, and three copies is three chances to translate it three
     * ways.
     */
    'actions' => [
        'browse' => 'Browse the templates',
        'use_template' => 'Use this template',
        'continue' => 'Continue',
        'back' => 'Back',
        // The site-wide primary action — the header, the hero, the pricing
        // card and the footer all repeat this one label on purpose.
        'start' => 'Get started',
    ],

    'nav' => [
        'templates' => 'Templates',
        'pricing' => 'Pricing',
        'language' => 'Language',
    ],

    /*
     * The footer's column headings and the one line under the wordmark. The
     * template links inside the columns reuse `templates.<slug>.label`, so the
     * columns never drift from the library.
     */
    'footer' => [
        'tagline' => 'Good websites for small businesses.',
        'product' => 'Product',
        'food' => 'Food & drink',
        'services' => 'Services & portfolios',
        'looks' => 'Looks',
        'faq' => 'FAQ',
    ],

    'home' => [
        'meta_description' => 'Pick a template or let AI draft one inside our design system. Answer a few questions, and your site is live on its own address in minutes.',

        'hero' => [
            // Doubles as the page's <title>: the headline and the search
            // result should make the same promise.
            'title' => 'A real website. A real business.',
            'intro' => 'Pick a template or let AI draft one. Answer a few questions about your business, and your site is live on its own address — photographs real, copy written, editor open.',
            'example' => 'See a finished example',
        ],

        /*
         * The stat band. Every number on it is checkable: the first two are
         * computed in the view (SiteTemplate::libraryCount(), the preset
         * count), and the other two restate promises made elsewhere on the
         * page — no invented user counts on a product this young.
         */
        'stats' => [
            'templates' => ['label' => 'finished templates'],
            'looks' => ['label' => 'looks in one design system'],
            'minutes' => ['value' => '≈4 min', 'label' => 'from picking to live'],
            'drag' => ['value' => '0', 'label' => 'boxes dragged'],
        ],

        /*
         * What the site DOES, one verb per card — scoped strictly to shipped
         * features (pages, posts, leads, locations, SEO, domains). Nothing
         * here may promise selling, payments or bookings.
         */
        'jobs' => [
            'title' => 'Put your site to work',
            'intro' => 'Not a brochure — a site that does the jobs a small business actually needs done.',
            'items' => [
                'menu' => [
                    'title' => 'Show your menu',
                    'body' => 'A priced menu section, filled in during signup.',
                ],
                'leads' => [
                    'title' => 'Catch every enquiry',
                    'body' => 'Form messages land in your dashboard, none lost.',
                ],
                'find' => [
                    'title' => 'Help people find you',
                    'body' => 'Address, hours and phone, bound to your real location.',
                ],
                'posts' => [
                    'title' => 'Post news and specials',
                    'body' => 'New dishes, events, holiday hours — posted in a minute.',
                ],
                'seo' => [
                    'title' => 'Get found on search',
                    'body' => 'Titles, descriptions and a sitemap, already wired.',
                ],
                'domain' => [
                    'title' => 'Bring your own domain',
                    'body' => 'Go live on a free address, switch to yours anytime.',
                ],
            ],
        ],

        /*
         * The two entry points, presented as equals. The AI card is framed as
         * "AI inside our design system", never as raw technology — and both
         * cards honestly lead to the gallery, because both paths really do run
         * through picking a template and the wizard.
         */
        'paths' => [
            'eyebrow' => 'No experience required',
            'title' => 'Two ways in. One finished site.',
            'ai' => [
                'title' => 'Let AI draft it',
                'body' => 'Answer a few questions and AI drafts the whole site inside our design system — layout, copy, sections. Every word stays editable.',
                'cta' => 'Start with AI',
            ],
            'template' => [
                'title' => 'Start from a template',
                'body' => ':count finished sites, each one fluent in your trade. Open the live demo, make it yours in minutes.',
                'cta' => 'Pick a template',
            ],
        ],

        'how' => [
            'eyebrow' => 'How it works',
            'title' => 'Three steps, and none of them are "drag a box"',
            'steps' => [
                'pick' => [
                    'title' => 'Pick a template',
                    'body' => ':count trades, each one a finished site rather than a wireframe. Open the live demo before you decide.',
                ],
                'answer' => [
                    'title' => 'Answer a few questions',
                    'body' => 'Your name, your address, a handful of things you sell. Skip any of it and the example copy stays.',
                ],
                'live' => [
                    'title' => 'Go live',
                    'body' => 'Your site is up on yourname.:host straight away, with the editor open on the home page.',
                ],
            ],
        ],

        'templates' => [
            'eyebrow' => 'Templates',
            'title' => 'Start from a site that already knows your trade',
            'intro' => 'Every one is a real, published site you can open right now — not a screenshot of an idea.',
        ],

        'design' => [
            'eyebrow' => 'One design system',
            // Counted, not typed. This line said "Seven looks" against eight
            // presets for two releases — the same failure SiteTemplate::
            // libraryCount() exists to prevent, and the one claim on the page a
            // visitor can check by counting the cards under it.
            'title' => ':count looks, and no way to make an ugly one',
            'intro' => 'You never pick a font size or a hex code. You pick a look, and every section on every page follows it — headings, spacing, corners, the lot.',
        ],

        /*
         * The made-with wall: our social proof is that the demo sites are
         * real, published and live right now — not screenshots of an idea.
         */
        'made' => [
            'eyebrow' => 'Real sites, live now',
            'title' => 'Every one of these is live right now',
            'intro' => 'Not mockups. Each one is a published site on its own address.',
            'all' => 'See all :count live demos',
        ],

        /*
         * PLACEHOLDER testimonials — replace all three with real customer
         * quotes before launch. No stock faces, same reason the templates
         * carry "no stock photograph pretending to be you".
         */
        'quotes' => [
            'title' => 'From people who run the till',
            'items' => [
                'one' => [
                    'quote' => 'Friday-night booking calls doubled.',
                    'name' => 'Ms. Chen',
                    'trade' => 'Sichuan restaurant',
                ],
                'two' => [
                    'quote' => 'People read the menu before they walk in now.',
                    'name' => 'Wang',
                    'trade' => 'Pizza shop',
                ],
                'three' => [
                    'quote' => 'I can’t design. No customer can tell.',
                    'name' => 'Amy',
                    'trade' => 'Nail salon',
                ],
            ],
        ],

        /*
         * The pricing teaser. The `price` value is a PLACEHOLDER — set the
         * real number before launch. One plan, no tiers: a young product
         * comparing three of its own columns reads as theatre.
         */
        'pricing' => [
            'eyebrow' => 'Pricing',
            'title' => 'One price. Everything in.',
            'intro' => 'Build first, decide when you see it — no card required.',
            'price' => '¥99',
            'period' => 'per month',
            'note' => 'Every feature, every template, every look. Nothing to compare.',
            'includes' => [
                'address' => 'Your own web address',
                'templates' => 'Every template and every look',
                'editor' => 'The visual editor',
                'seo' => 'SEO wired in',
                'forms' => 'Enquiry forms into your dashboard',
            ],
        ],

        /*
         * Objection handling, in the order people actually raise them. `:host`
         * and `:count` are substituted in the view, same as `how.steps`.
         */
        'faq' => [
            'title' => 'Questions, answered',
            'items' => [
                'speed' => [
                    'q' => 'How long until my site is live?',
                    'a' => 'Minutes. Pick a template, answer a few questions, and it is running on yourname.:host.',
                ],
                'design' => [
                    'q' => 'I’m not a designer — will it look bad?',
                    'a' => 'No. You pick a look; sizes, spacing and colour are already decided. :count looks, switchable anytime.',
                ],
                'domain' => [
                    'q' => 'Can I use my own domain?',
                    'a' => 'Yes. Go live on the free address first, connect your own domain whenever you’re ready.',
                ],
                'ai' => [
                    'q' => 'Will the AI copy sound fake?',
                    'a' => 'AI drafts inside your trade’s template and our design system, and every word stays editable. Skip a question and the written example stays.',
                ],
                'edit' => [
                    'q' => 'Can I change things after it’s live?',
                    'a' => 'Always. The editor is always open — a word, a photo, or the whole look.',
                ],
                'seo' => [
                    'q' => 'Will search engines find me?',
                    'a' => 'Yes. Every site ships with SEO wired in — titles, descriptions and a sitemap.',
                ],
                'pay' => [
                    'q' => 'Do I pay before I see it?',
                    'a' => 'No. No card — your site goes live first, decide when you’ve seen it.',
                ],
                'leads' => [
                    'q' => 'Can customers reach me through the site?',
                    'a' => 'Yes. Every form message lands in your dashboard.',
                ],
            ],
        ],

        'cta' => [
            'title' => 'Your site is about four minutes away',
            'intro' => 'No card, no call, no blank page. Pick the template that fits and start filling it in.',
        ],
    ],

    'gallery' => [
        'meta_title' => 'Website templates for small businesses',
        'meta_description' => ':count finished websites, one for each trade. Open the live demo, then make it yours in a few minutes.',
        'eyebrow' => 'Templates',
        'title' => ':count finished sites. Pick the one that fits.',
        'intro' => 'Each one is a real published site, not a wireframe — open the live demo, read the copy, scroll it on your phone. When you find the right one, it takes a few minutes to make it yours.',
    ],

    'card' => [
        'alt' => 'The :template template',
        'cta' => 'See the template',
    ],

    'detail' => [
        'meta_title' => ':template website template',
        'back' => 'All templates',
        'phone_alt' => 'The :template template on a phone',
        'iframe_title' => 'Live demo of the :template template',

        'look' => [
            'title' => 'The look',
            'note' => 'Change it whenever you like — every section on every page follows the look you pick.',
        ],

        'pages' => [
            'title' => 'What you get',
            'sections' => ':count sections',
        ],

        'questions' => [
            'title' => 'What we’ll ask you',
            'note' => 'All optional — skip them and the example copy stays.',
        ],

        'mobile' => [
            'title' => 'It reads just as well on a phone',
        ],

        'demo' => [
            'title' => 'The live demo',
            'served_from' => 'This is the real site, served from :host.',
            'view' => 'View the live demo',
            'open' => 'Open the live demo',
        ],

        'cta' => [
            'title' => 'Make it yours',
            'intro' => 'Answer a few questions and this site goes live on your own address, ready to edit.',
        ],
    ],

    'wizard' => [
        'title' => 'Let’s build your site',
        'progress' => 'Progress',
        'optional' => '(optional)',

        'steps' => [
            'business' => 'Your business',
            'content' => 'Your content',
            'account' => 'Your account',
        ],

        'business_name' => 'What is the business called?',
        'address' => 'Your web address',
        'use_suggestion' => 'Use :subdomain instead',
        'available' => ':domain is free',
        'tagline' => 'One line about the business',
        'city' => 'Town or city',
        'phone' => 'Phone',

        'content_intro' => 'These go straight onto your pages. Leave any of them blank — or skip the lot — and the template’s example content stays until you change it in the editor.',
        'skip' => 'Skip — use example content, edit later',

        'account_intro' => 'Last step. This is the account you’ll sign in with to edit your site.',
        'email' => 'Email',
        'password' => 'Password',
        // The honeypot's label. Never seen by a person, so it exists only to
        // read plausibly to a form filler.
        'honeypot' => 'Website',
        'submit' => 'Build my site',
        'submitting' => 'Building your site…',
        'rate_limited' => 'Too many sites created from this connection. Try again in :minutes minutes.',

        'done' => [
            'eyebrow' => 'Your site is live',
            'title' => ':business is on the internet',
            // :url arrives already wrapped in the markup that emphasises it,
            // so each language can punctuate the sentence its own way.
            'published' => 'It’s published at :url.',
            'drafts' => 'The pages are saved as drafts so you can read them over before anyone else does — open the editor and hit publish when you’re happy. Photographs are still landing; give them a minute.',
            'open_editor' => 'Open the editor',
            'view_site' => 'View my site',
        ],
    ],

    'not_found' => [
        'title' => 'Page not found',
        'body' => 'That link does not lead anywhere. The templates are a better place to start.',
        'cta' => 'Browse templates',
    ],

    /*
     * The wizard's web-address validation. Here rather than in validation.php
     * because they are marketing-site copy in the same voice as the questions
     * above them, not framework rule messages.
     *
     * @see \App\Actions\Templates\ValidateSubdomain
     */
    'subdomain' => [
        'invalid' => 'Use 3 to 63 letters, numbers or hyphens — for example "corner-cafe".',
        'reserved' => 'That address is reserved. Please choose another.',
        'taken' => 'That address is already taken.',
    ],

    /*
     * The marketing copy for every hand-curated template: its name, the line on
     * its gallery card, the three reasons to pick it, and the wizard questions it
     * asks.
     *
     * Keyed by SiteTemplate::$value, then by TemplateField::$key. Two levels rather
     * than one flat field map on purpose — `service_one` means "your most-booked
     * service" in the hair studio and "the service you are known for" in the nail
     * salon, so a shared key would have to pick one.
     *
     * What is NOT here: the templates' own page content and the `example` values
     * behind each field. Those are seeded into a tenant's `pages` rows when the
     * template is applied, so translating them is a separate job with a data
     * migration attached to it.
     *
     * @see \App\Templates\SiteTemplate::label()
     * @see \App\Templates\SiteTemplate::fieldLabel()
     */
    'templates' => [
        'chinese-restaurant' => [
            'label' => 'Chinese restaurant',
            'description' => 'A menu, a story and a map. Built for a family kitchen that fills up on Friday nights.',
            'highlights' => [
                'A priced menu section you fill in during signup',
                'Address, hours and phone bound to your real location',
                'A story page that is already written',
            ],
            'fields' => [
                'dish_one' => ['label' => 'Your signature dish'],
                'dish_one_price' => ['label' => 'Its price'],
                'dish_two' => ['label' => 'A second favourite'],
                'dish_two_price' => ['label' => 'Its price'],
                'dish_three' => ['label' => 'One more'],
                'dish_three_price' => ['label' => 'Its price'],
            ],
        ],

        'pizza-shop' => [
            'label' => 'Pizza shop',
            'description' => 'Warm, loud and hungry. The neighbourhood pizzeria that people order from twice a week.',
            'highlights' => [
                'Three signature pizzas with prices, straight from the form',
                'A call-to-order banner on every page',
                'Photography chosen for wood-fired warmth',
            ],
            'fields' => [
                'pizza_one' => [
                    'label' => 'The pizza you are known for',
                    'help' => 'It leads the menu section, so pick the one you would want a first-timer to order.',
                ],
                'pizza_one_price' => ['label' => 'Its price'],
                'pizza_two' => ['label' => 'A second pizza'],
                'pizza_two_price' => ['label' => 'Its price'],
                'pizza_three' => ['label' => 'One more'],
                'pizza_three_price' => ['label' => 'Its price'],
            ],
        ],

        'burger-joint' => [
            'label' => 'Burger joint',
            'description' => 'Big type, big photographs, no fuss. For a counter with a queue out the door.',
            'highlights' => [
                'Headline type sized for a photograph, not a paragraph',
                'A menu block you can extend to the full board',
                'Loud, casual copy you can keep as written',
            ],
            'fields' => [
                'burger_one' => [
                    'label' => 'Your house burger',
                    'help' => 'The one people say the name of when they walk in.',
                ],
                'burger_one_price' => ['label' => 'Its price'],
                'burger_two' => ['label' => 'A second burger'],
                'burger_two_price' => ['label' => 'Its price'],
                'burger_three' => ['label' => 'One more'],
                'burger_three_price' => ['label' => 'Its price'],
            ],
        ],

        'bubble-tea' => [
            'label' => 'Bubble tea shop',
            'description' => 'Bright and product-forward, with room for a seasonal menu that changes every month.',
            'highlights' => [
                'A seasonal-specials section built to be swapped monthly',
                'Pastel product photography from the shared library',
                'A clean geometric grid that survives long drink names',
            ],
            'fields' => [
                'drink_one' => [
                    'label' => 'Your signature drink',
                    'help' => 'The one you would put in the window. It leads the menu.',
                ],
                'drink_one_price' => ['label' => 'Its price'],
                'drink_two' => ['label' => 'A second favourite'],
                'drink_two_price' => ['label' => 'Its price'],
                'seasonal_drink' => ['label' => 'What is on special right now'],
                'seasonal_drink_price' => ['label' => 'Its price'],
            ],
        ],

        'fine-dining' => [
            'label' => 'Fine dining',
            'description' => 'Dark, quiet and reservation-first. For the dining room people book a week ahead.',
            'highlights' => [
                'A table-request form with date, time and party size',
                'A three-act menu with course headings and prices',
                'An embedded map and live opening hours on every visit section',
            ],
            'fields' => [
                'starter_dish' => [
                    'label' => 'A starter you are known for',
                    'help' => 'It opens the menu section, so pick the plate regulars will not let you retire.',
                ],
                'starter_dish_price' => ['label' => 'Its price'],
                'main_dish' => ['label' => 'The main event'],
                'main_dish_price' => ['label' => 'Its price'],
                'dessert_dish' => ['label' => 'The dessert to finish on'],
                'dessert_dish_price' => ['label' => 'Its price'],
            ],
        ],

        'sushi-bar' => [
            'label' => 'Sushi bar',
            'description' => 'Restrained and precise, with room to breathe. Built for a counter that seats twelve.',
            'highlights' => [
                'A seat-request form sized for an omakase counter',
                'A grouped menu for omakase, nigiri and rolls',
                'Quiet reveal animations that match the pace of the room',
            ],
            'fields' => [
                'omakase_name' => [
                    'label' => 'Your omakase, as the menu names it',
                    'help' => 'The set the counter is known for — it leads the menu.',
                ],
                'omakase_price' => ['label' => 'Its price per person'],
                'nigiri_one' => ['label' => 'A nigiri you are proud of'],
                'nigiri_one_price' => ['label' => 'Its price'],
                'roll_one' => ['label' => 'A roll people come back for'],
                'roll_one_price' => ['label' => 'Its price'],
            ],
        ],

        'cafe-brunch' => [
            'label' => 'Café & brunch',
            'description' => 'Bright, warm and morning-shaped. Walk-ins on weekdays, booked-out brunch on weekends.',
            'highlights' => [
                'A weekend-brunch booking form with date, time and party size',
                'A grouped menu for brunch, bakes and coffee',
                'A mid-page signup block for the loyalty offer',
            ],
            'fields' => [
                'brunch_one' => [
                    'label' => 'The brunch plate you are known for',
                    'help' => 'It leads the menu — the dish people photograph before they eat.',
                ],
                'brunch_one_price' => ['label' => 'Its price'],
                'brunch_two' => ['label' => 'A second brunch favourite'],
                'brunch_two_price' => ['label' => 'Its price'],
                'signature_coffee' => ['label' => 'The coffee to order'],
                'signature_coffee_price' => ['label' => 'Its price'],
            ],
        ],

        'family-bistro' => [
            'label' => 'Family bistro',
            'description' => 'Warm, generous and unfussy — the neighbourhood table booked for birthdays and Sunday lunch.',
            'highlights' => [
                'A table-booking form sized for big family groups',
                'A menu with a kids section built in',
                'Copy written for birthdays, regulars and Sunday lunch',
            ],
            'fields' => [
                'main_one' => [
                    'label' => 'The main everyone orders',
                    'help' => 'It leads the menu — the plate the table shares stories about.',
                ],
                'main_one_price' => ['label' => 'Its price'],
                'main_two' => ['label' => 'A second favourite'],
                'main_two_price' => ['label' => 'Its price'],
                'kids_meal' => ['label' => 'What the kids get'],
                'kids_meal_price' => ['label' => 'Its price'],
            ],
        ],

        'nail-salon' => [
            'label' => 'Nail salon',
            'description' => 'A dark room, gold accents and a gallery of your work directly under the fold.',
            'highlights' => [
                'A nail-art gallery immediately under the hero',
                'A priced service menu, filled in during signup',
                'The one dark preset in the library, with gold accents',
            ],
            'fields' => [
                'service_one' => [
                    'label' => 'The service you are known for',
                    'help' => 'It leads the menu on both pages.',
                ],
                'service_one_price' => ['label' => 'Its price'],
                'service_two' => ['label' => 'A second service'],
                'service_two_price' => ['label' => 'Its price'],
                'service_three' => ['label' => 'Something for feet'],
                'service_three_price' => ['label' => 'Its price'],
            ],
        ],

        'hair-studio' => [
            'label' => 'Hair studio',
            'description' => 'A screenful of type before a single photograph. For a studio whose waiting list is the pitch.',
            'highlights' => [
                'A full-screen opening in type alone — no stock photograph above the fold',
                'A cutting and colour menu with real times and prices',
                'The one look in the library whose sections arrive as you scroll',
            ],
            'fields' => [
                'service_one' => ['label' => 'Your most-booked service'],
                'service_one_price' => [
                    'label' => 'How long it runs and what it costs',
                    'help' => 'Length first, then price — it reads as one line on the menu.',
                ],
                'service_two' => ['label' => 'A second service'],
                'service_two_price' => ['label' => 'Its length and price'],
                'service_three' => ['label' => 'One more'],
                'service_three_price' => ['label' => 'Its length and price'],
            ],
        ],

        'massage-spa' => [
            'label' => 'Massage & spa',
            'description' => 'Airy and unhurried, with one calm way to book. Nothing on the page raises its voice.',
            'highlights' => [
                'A tall, quiet hero with a single call to action',
                'A treatment menu with durations and prices',
                'Spacing tuned so nothing on the page hurries you',
            ],
            'fields' => [
                'treatment_one' => ['label' => 'Your most-booked treatment'],
                'treatment_one_price' => [
                    'label' => 'How long it runs and what it costs',
                    'help' => 'Length first, then price — it reads as one line on the menu.',
                ],
                'treatment_two' => ['label' => 'A second treatment'],
                'treatment_two_price' => ['label' => 'Its length and price'],
                'treatment_three' => ['label' => 'One more'],
                'treatment_three_price' => ['label' => 'Its length and price'],
            ],
        ],

        'personal-resume' => [
            'label' => 'Personal resume',
            'description' => 'Your name, your work and a way to reach you. Type only — no stock photograph in sight.',
            'highlights' => [
                'A type-only hero: your name, your title, one line',
                'Three experience entries you fill in during signup',
                'No stock photograph pretending to be you',
            ],
            'fields' => [
                'current_title' => [
                    'label' => 'Your title right now',
                    'help' => 'One line. It sits directly under your name in the hero.',
                ],
                'role_one' => ['label' => 'Your current or most recent role'],
                'role_two' => ['label' => 'The one before it'],
                'role_three' => ['label' => 'And the one before that'],
                'focus' => ['label' => 'The work you want more of'],
            ],
        ],

        'designer-portfolio' => [
            'label' => 'Designer portfolio',
            'description' => 'Magazine layout, work in the first viewport. For studios whose portfolio does the selling.',
            'highlights' => [
                'Your work on black, in the first viewport',
                'Three project write-ups from the signup form',
                'A services page with real engagement shapes and prices',
            ],
            'fields' => [
                'discipline' => [
                    'label' => 'What you actually do',
                    'help' => 'One line. It goes under your name in the hero.',
                ],
                'project_one' => ['label' => 'A project to lead with'],
                'project_two' => ['label' => 'A second project'],
                'project_three' => ['label' => 'A third project'],
            ],
        ],
    ],

    /*
     * The style presets as the MARKETING site describes them, keyed by
     * StylePreset::$value.
     *
     * A deliberate fork from StylePreset::label()/description(), which stay hard
     * English literals. Those two feed the tenant panel's preset picker AND two AI
     * system prompts (SiteDraftPrompt, PageEditPrompt), where the description plus
     * its synonyms are the whole grounding for a request like "make it more
     * premium". Translating the strings the model reasons over would quietly change
     * which preset it picks, so the marketing surface gets its own copy instead and
     * the prompt keeps the English it was tuned against.
     *
     * @see \App\Design\StylePreset
     * @see \App\Ai\Prompts\SiteDraftPrompt::presetsSection()
     */
    'presets' => [
        'warm-craft' => [
            'label' => 'Warm craft',
            'description' => 'Earthy tones, elegant serifs and generous spacing — for artisan and hospitality businesses.',
        ],
        'professional-minimal' => [
            'label' => 'Professional minimal',
            'description' => 'Monochrome, tight and typographic — for services that sell trust.',
        ],
        'fresh-modern' => [
            'label' => 'Fresh modern',
            'description' => 'Green-tinted, geometric and energetic — for modern brands.',
        ],
        'bold-editorial' => [
            'label' => 'Bold editorial',
            'description' => 'High-contrast plum, sharp corners and magazine typography.',
        ],
        'calm-coastal' => [
            'label' => 'Calm coastal',
            'description' => 'Cool blues, soft corners and airy spacing — for wellness and care.',
        ],
        'playful-friendly' => [
            'label' => 'Playful friendly',
            'description' => 'Sunset colors, round shapes and a friendly voice.',
        ],
        'night-lounge' => [
            'label' => 'Night lounge',
            'description' => 'Near-black and gold, heavy type on a dark room — the one dark-site preset.',
        ],
        'quiet-luxe' => [
            'label' => 'Quiet luxe',
            'description' => 'Warm stone, hairline serifs set very large, square corners and sections that arrive as you scroll.',
        ],
    ],
];
