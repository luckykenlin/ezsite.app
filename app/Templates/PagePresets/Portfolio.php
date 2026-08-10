<?php

declare(strict_types=1);

namespace App\Templates\PagePresets;

use App\Templates\PagePresetDefinition;

/**
 * The selected-work page: the quiet full-viewport hero (the same restraint
 * the resume and designer templates use), a tight grid of pieces, the numbers
 * that establish the track record, and a soft ask.
 *
 * Stats rather than a logos row, deliberately: logos render nothing without
 * real artwork (the view skips url-less entries), so a fresh page would show
 * an empty band where the trust was supposed to be. Numbers hold the same
 * slot and render honestly as `00` placeholders.
 */
final readonly class Portfolio
{
    public static function definition(): PagePresetDefinition
    {
        return new PagePresetDefinition(
            title: 'Portfolio',
            metaDescription: 'Selected work by {business_name} — {tagline}.',
            blocks: [
                ['type' => 'hero', 'variant' => 'full-viewport-quiet', 'data' => [
                    'eyebrow' => '{business_name}',
                    'heading' => 'Selected work',
                    'subheading' => 'One line on the kind of work you take, and what you care about in it.',
                    'image_query' => 'minimal studio workspace',
                ]],
                ['type' => 'gallery', 'variant' => 'grid', 'data' => [
                    'heading' => 'Recent projects',
                    'images' => [
                        ['alt' => 'Replace with a project photo'],
                        ['alt' => 'Replace with a project photo'],
                        ['alt' => 'Replace with a project photo'],
                        ['alt' => 'Replace with a project photo'],
                        ['alt' => 'Replace with a project photo'],
                        ['alt' => 'Replace with a project photo'],
                    ],
                    'image_query' => 'design portfolio project detail',
                ]],
                ['type' => 'stats', 'data' => [
                    'heading' => 'The track record',
                    'stats' => [
                        ['value' => '00', 'label' => 'Projects delivered'],
                        ['value' => '00', 'label' => 'Years of work'],
                        ['value' => '00', 'label' => 'Replace with a number you are proud of'],
                    ],
                ]],
                ['type' => 'cta', 'variant' => 'banner', 'data' => [
                    'heading' => 'Have a project in mind?',
                    'body' => 'Say what makes a good first message — budget, timeline, or just the idea.',
                    'cta_label' => 'Start a conversation',
                    'cta_url' => '/contact',
                ]],
            ],
        );
    }
}
