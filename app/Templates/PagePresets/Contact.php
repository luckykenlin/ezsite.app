<?php

declare(strict_types=1);

namespace App\Templates\PagePresets;

use App\Templates\PagePresetDefinition;

/**
 * The contact page: a short hero, the enquiry form with the live address and
 * hours beside it (the contact block binds the primary location, so the
 * factual half fills itself), and the questions people ask before visiting.
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
                ['type' => 'contact', 'data' => [
                    'heading' => 'Where to find us',
                    'intro' => 'Add a line about parking, the entrance, or anything that makes arriving easier.',
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
