<?php

declare(strict_types=1);

namespace App\Themes\Definitions\DefaultTheme\Pages;

use App\Themes\ThemePage;
use Filament\Support\Icons\Heroicon;

/**
 * The selected-work page: the quiet full-viewport hero, a tight grid of
 * pieces, and a soft ask. Nothing between the work and the ask, deliberately:
 * a trust band would need real numbers or real artwork, and a theme page must
 * never invent either.
 */
final readonly class Portfolio
{
    public static function definition(): ThemePage
    {
        return new ThemePage(
            key: 'portfolio',
            label: 'Portfolio',
            description: 'Selected work, presented quietly and confidently.',
            icon: Heroicon::OutlinedRectangleStack,
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
