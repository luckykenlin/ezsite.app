<?php

declare(strict_types=1);

namespace App\Templates\PagePresets;

use App\Templates\PagePresetDefinition;

/**
 * The landing-page preset: headline, the practical strip, why-us, proof, ask.
 *
 * The strip sits second, directly under the hero, and that placement is the
 * point of it. For the businesses this builder is for — a takeaway, a salon, a
 * bubble tea counter — the first question is "are you open and where are you",
 * not "why should I choose you"; the brochure arc answered it last or not at
 * all. It binds the primary location, so it fills itself.
 *
 * Trade-agnostic where the template definitions are trade-specific: the copy
 * leans on the profile tokens (`{business_name}`, `{tagline}`, `{city}`) for
 * everything factual and reads as an obvious replace-me everywhere it cannot
 * know the answer. The testimonial is a placeholder by design — a preset must
 * never invent a customer.
 */
final readonly class Home
{
    public static function definition(): PagePresetDefinition
    {
        return new PagePresetDefinition(
            title: 'Home',
            metaDescription: '{business_name} — {tagline}. Based in {city}.',
            blocks: [
                ['type' => 'hero', 'variant' => 'full-bleed-overlay', 'data' => [
                    'eyebrow' => '{city}',
                    'heading' => '{tagline}',
                    'subheading' => 'Welcome to {business_name}. Use this line to say, in plain words, what you do and who it is for.',
                    'cta_label' => 'Get in touch',
                    'cta_url' => '/contact',
                    'image_query' => 'welcoming small business interior',
                ]],
                ['type' => 'visit', 'data' => [
                    'heading' => 'Find us',
                    'intro' => 'Open hours, the address, and the quickest way to reach us.',
                    'show_hours' => true,
                ]],
                ['type' => 'features', 'variant' => 'grid', 'data' => [
                    'heading' => 'Why {business_name}',
                    'intro' => 'Three reasons customers choose you — swap these for your own.',
                    'features' => [
                        ['title' => 'Name a strength', 'description' => 'Replace this with a benefit your customers actually care about.'],
                        ['title' => 'Name another', 'description' => 'Keep each one to a sentence — specifics beat superlatives.'],
                        ['title' => 'Local to {city}', 'description' => 'Say what being nearby means for the customer, not for you.'],
                    ],
                ]],
                ['type' => 'testimonials', 'variant' => 'spotlight', 'data' => [
                    'heading' => 'What customers say',
                    'testimonials' => [
                        ['quote' => 'Replace this with a real quote from a happy customer — one honest sentence sells better than a paragraph you write yourself.', 'author' => 'A happy customer', 'role' => '{city}'],
                    ],
                ]],
                ['type' => 'cta', 'variant' => 'banner', 'data' => [
                    'heading' => 'Ready when you are',
                    'body' => 'Tell visitors what to do next, and why now is the right time.',
                    'cta_label' => 'Call {phone}',
                    'cta_url' => 'tel:{phone}',
                    'secondary_label' => 'Send a message',
                    'secondary_url' => '/contact',
                ]],
            ],
        );
    }
}
