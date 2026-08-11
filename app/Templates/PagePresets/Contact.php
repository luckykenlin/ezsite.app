<?php

declare(strict_types=1);

namespace App\Templates\PagePresets;

use App\Templates\PagePresetDefinition;

/**
 * The contact page: a short hero, the practical strip (open now, address,
 * number), then the enquiry form, then the questions people ask before
 * visiting.
 *
 * The strip sits ABOVE the form deliberately. Most people arriving here from a
 * search are not writing a message — they want to know whether it is worth
 * setting off — and the page used to make them scroll past a form to find out.
 * Both blocks bind the primary location, so the factual halves fill themselves.
 */
final readonly class Contact
{
    public static function definition(): PagePresetDefinition
    {
        return new PagePresetDefinition(
            title: 'Contact',
            metaDescription: 'Address, hours and contact details for {business_name} in {city}.',
            blocks: [
                ['type' => 'hero', 'variant' => 'centered-minimal', 'data' => [
                    'eyebrow' => '{city}',
                    'heading' => 'Get in touch',
                    'subheading' => 'Say how you prefer to be reached, and how quickly people can expect to hear back.',
                ]],
                ['type' => 'visit', 'data' => [
                    'heading' => 'Where to find us',
                    'intro' => 'Walk in, or call ahead and we will have it ready.',
                    'show_hours' => true,
                    'note' => 'Add a line about parking, the entrance, or anything that makes arriving easier.',
                ]],
                ['type' => 'contact', 'data' => [
                    'show_hours' => false,
                    'heading' => 'Send us a message',
                    'intro' => 'Tell us what you need and we will come back to you.',
                    'show_form' => true,
                    'success_message' => 'Thank you — we will get back to you as soon as we can.',
                ]],
                ['type' => 'faq', 'data' => [
                    'heading' => 'Before you visit',
                    'questions' => [
                        ['question' => 'Do I need to book ahead?', 'answer' => 'Replace this with your own answer — a sentence or two is plenty.'],
                        ['question' => 'Where can I park?', 'answer' => 'Replace this with the honest local answer, including the free option.'],
                        ['question' => 'How do you take payment?', 'answer' => 'Replace this with the ways you accept payment.'],
                    ],
                ]],
            ],
        );
    }
}
