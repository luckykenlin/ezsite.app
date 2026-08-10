<?php

declare(strict_types=1);

namespace App\Templates\PagePresets;

use App\Templates\PagePresetDefinition;

/**
 * The offer page: what you sell with prices, how an engagement runs, proof,
 * then the ask. Offerings over features on purpose — this page exists for
 * things a customer can BUY, and the `$00` prices are loud placeholders that
 * demand replacing rather than quiet lies.
 */
final readonly class Services
{
    public static function definition(): PagePresetDefinition
    {
        return new PagePresetDefinition(
            title: 'Services',
            metaDescription: 'Services and prices at {business_name} in {city}, and how to book.',
            blocks: [
                ['type' => 'hero', 'variant' => 'centered-minimal', 'data' => [
                    'eyebrow' => 'Services',
                    'heading' => 'What we offer',
                    'subheading' => 'A sentence on the range of what you do, and the promise that the price below is the price.',
                ]],
                ['type' => 'offerings', 'data' => [
                    'heading' => 'Services and prices',
                    'intro' => 'Replace these with your own — group them if the list gets long.',
                    'items' => [
                        ['group' => 'Popular', 'name' => 'Your most-asked-for service', 'price' => '$00', 'description' => 'Replace this with what the customer gets and how long it takes.'],
                        ['group' => 'Popular', 'name' => 'Your second service', 'price' => '$00', 'description' => 'Replace this with what the customer gets and how long it takes.'],
                        ['group' => 'Also available', 'name' => 'A third service', 'price' => 'from $00', 'description' => 'Use "from" pricing when the job varies — and say what moves the price.'],
                    ],
                ]],
                ['type' => 'steps', 'variant' => 'list', 'data' => [
                    'heading' => 'How it works',
                    'intro' => 'From first contact to finished job.',
                    'steps' => [
                        ['title' => 'Get in touch', 'description' => 'Replace this with what the customer does first — call, message or book online.'],
                        ['title' => 'We agree the details', 'description' => 'Replace this with what happens next: the quote, the appointment, the plan.'],
                        ['title' => 'The work gets done', 'description' => 'Replace this with how it finishes, and what happens if something is not right.'],
                    ],
                ]],
                ['type' => 'testimonials', 'variant' => 'carousel', 'data' => [
                    'heading' => 'From recent customers',
                    'testimonials' => [
                        ['quote' => 'Replace this with a real quote about a specific service — the more specific, the more convincing.', 'author' => 'A happy customer', 'role' => '{city}'],
                        ['quote' => 'Replace this with a second quote, or delete it — one real voice beats two invented ones.', 'author' => 'A happy customer', 'role' => '{city}'],
                    ],
                ]],
                ['type' => 'cta', 'variant' => 'full-photo', 'data' => [
                    'heading' => 'Book with {business_name}',
                    'body' => 'Say how quickly you usually respond, and what the customer should have ready.',
                    'cta_label' => 'Call {phone}',
                    'cta_url' => 'tel:{phone}',
                    'image_query' => 'professional service work close up',
                ]],
            ],
        );
    }
}
