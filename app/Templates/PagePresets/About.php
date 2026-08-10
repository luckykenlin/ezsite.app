<?php

declare(strict_types=1);

namespace App\Templates\PagePresets;

use App\Templates\PagePresetDefinition;

/**
 * The story page: prose first because an about page is the one place a
 * visitor arrives wanting to READ, then numbers and faces to back the story
 * up. Stats and team members are deliberately zeroed placeholders — a preset
 * must never invent a track record or a person.
 */
final readonly class About
{
    public static function definition(): PagePresetDefinition
    {
        return new PagePresetDefinition(
            title: 'About',
            metaDescription: 'The story behind {business_name} in {city} — who we are and how we work.',
            blocks: [
                ['type' => 'hero', 'variant' => 'left-text-right-image', 'data' => [
                    'eyebrow' => 'About us',
                    'heading' => 'The story behind {business_name}',
                    'subheading' => 'One or two sentences on how it started and what you stand for — the details go below.',
                    'image_query' => 'small business owner at work candid',
                ]],
                ['type' => 'prose', 'variant' => 'side-heading', 'data' => [
                    'heading' => 'How we got here',
                    'paragraphs' => [
                        ['text' => 'Replace this with how {business_name} started — the year, the reason, the first customer. Origin stories build more trust than adjectives.'],
                        ['text' => 'Add a second paragraph about how you work today and what you refuse to compromise on. Short paragraphs read better on a phone.'],
                    ],
                ]],
                ['type' => 'stats', 'data' => [
                    'heading' => 'By the numbers',
                    'stats' => [
                        ['value' => '00', 'label' => 'Years in {city}'],
                        ['value' => '00', 'label' => 'Customers served'],
                        ['value' => '00', 'label' => 'Replace with a number you are proud of'],
                    ],
                ]],
                ['type' => 'team', 'data' => [
                    'heading' => 'The people',
                    'intro' => 'The faces a customer will actually deal with.',
                    'members' => [
                        ['name' => 'Add a real name', 'role' => 'Their role', 'bio' => 'Replace this with a sentence about them.'],
                        ['name' => 'Add a real name', 'role' => 'Their role', 'bio' => 'Replace this with a sentence about them.'],
                    ],
                ]],
                ['type' => 'cta', 'variant' => 'banner', 'data' => [
                    'heading' => 'Come and see for yourself',
                    'body' => 'The quickest way to know if we are right for you is to ask.',
                    'cta_label' => 'Get in touch',
                    'cta_url' => '/contact',
                ]],
            ],
        );
    }
}
