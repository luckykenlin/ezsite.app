<?php

declare(strict_types=1);

namespace App\Themes\Definitions\DefaultTheme\Pages;

use App\Themes\ThemePage;
use Filament\Support\Icons\Heroicon;

/**
 * The pictures page: a bare heading, one big masonry wall, and a single ask
 * at the end. Deliberately the quietest page — on a gallery page every
 * sentence competes with the photographs, so there are almost none. Images
 * are alt-only entries the stock-photo pipeline (or the operator's own
 * uploads) fills, exactly as the template definitions author theirs.
 */
final readonly class Gallery
{
    public static function definition(): ThemePage
    {
        return new ThemePage(
            key: 'gallery',
            label: 'Gallery',
            description: 'Let the photographs do the talking.',
            icon: Heroicon::OutlinedPhoto,
            title: 'Gallery',
            metaDescription: 'Photos of {business_name} in {city} and the work we do.',
            blocks: [
                ['type' => 'heading', 'data' => [
                    'content' => 'A look around {business_name}',
                    'level' => 'h1',
                ]],
                ['type' => 'gallery', 'variant' => 'masonry', 'data' => [
                    'heading' => 'Recent photos',
                    'images' => [
                        ['alt' => 'Replace with a photo of your work'],
                        ['alt' => 'Replace with a photo of your space'],
                        ['alt' => 'Replace with a photo of the details'],
                        ['alt' => 'Replace with a photo of your work'],
                        ['alt' => 'Replace with a photo people ask about'],
                        ['alt' => 'Replace with a favourite'],
                    ],
                    'image_query' => 'small business craft work detail',
                ]],
                ['type' => 'cta', 'variant' => 'banner', 'data' => [
                    'heading' => 'Like what you see?',
                    'body' => 'Tell visitors how to get the same for themselves.',
                    'cta_label' => 'Get in touch',
                    'cta_url' => '/contact',
                ]],
            ],
        );
    }
}
