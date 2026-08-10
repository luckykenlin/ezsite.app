<?php

declare(strict_types=1);

namespace App\Templates\PagePresets;

use App\Templates\PagePresetDefinition;

/**
 * The people page: a bare heading (a divider, not a second hero — the team
 * block carries its own introduction), the faces, a word from customers about
 * them, and the ask. Every member is an explicit placeholder: the block's own
 * contract says never to invent a person, and a preset is bound by it too.
 */
final readonly class Team
{
    public static function definition(): PagePresetDefinition
    {
        return new PagePresetDefinition(
            title: 'Team',
            metaDescription: 'Meet the people behind {business_name} in {city}.',
            blocks: [
                ['type' => 'heading', 'data' => [
                    'content' => 'The people behind {business_name}',
                    'level' => 'h1',
                ]],
                ['type' => 'team', 'data' => [
                    'heading' => 'Meet the team',
                    'intro' => 'The people a customer will actually deal with — add a photo and a line for each.',
                    'members' => [
                        ['name' => 'Add a real name', 'role' => 'Their role', 'bio' => 'Replace this with a sentence about them — what they do, and how long they have done it.'],
                        ['name' => 'Add a real name', 'role' => 'Their role', 'bio' => 'Replace this with a sentence about them.'],
                        ['name' => 'Add a real name', 'role' => 'Their role', 'bio' => 'Replace this with a sentence about them.'],
                    ],
                ]],
                ['type' => 'testimonials', 'variant' => 'grid', 'data' => [
                    'heading' => 'What customers say about them',
                    'testimonials' => [
                        ['quote' => 'Replace this with a real quote that names someone on the team — nothing builds trust in people faster.', 'author' => 'A happy customer', 'role' => '{city}'],
                        ['quote' => 'Replace this with a second quote, or delete it.', 'author' => 'A happy customer', 'role' => '{city}'],
                    ],
                ]],
                ['type' => 'cta', 'variant' => 'banner', 'data' => [
                    'heading' => 'Talk to us',
                    'body' => 'Whoever answers, you are in good hands.',
                    'cta_label' => 'Get in touch',
                    'cta_url' => '/contact',
                ]],
            ],
        );
    }
}
