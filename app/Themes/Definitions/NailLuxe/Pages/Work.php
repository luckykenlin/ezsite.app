<?php

declare(strict_types=1);

namespace App\Themes\Definitions\NailLuxe\Pages;

use App\Themes\ThemePage;
use Filament\Support\Icons\Heroicon;

/**
 * The portfolio. A client deciding on a studio is deciding on the pictures,
 * so the work gets two galleries — arched tiles for the signature sets and a
 * mosaic for everything else — with a client's word between them.
 */
final readonly class Work
{
    public static function definition(): ThemePage
    {
        return new ThemePage(
            key: 'work',
            label: 'Work',
            description: 'A portfolio of recent sets, with a word from a client.',
            icon: Heroicon::OutlinedPhoto,
            title: 'Work',
            metaDescription: 'Recent nail art and manicures by {business_name} in {city}.',
            blocks: [
                ['type' => 'hero', 'variant' => 'editorial', 'data' => [
                    'eyebrow' => 'Portfolio',
                    'heading' => 'Recent *work*',
                    'subheading' => 'Replace this with a line about the styles you love to paint.',
                ]],
                ['type' => 'gallery', 'variant' => 'arch-grid', 'data' => [
                    'heading' => 'Signature *sets*',
                    'images' => [
                        ['alt' => 'Replace with a photo of a signature set'],
                        ['alt' => 'Replace with a photo of a signature set'],
                        ['alt' => 'Replace with a photo of a signature set'],
                    ],
                    'image_query' => 'nail art manicure close up',
                ]],
                ['type' => 'testimonials', 'variant' => 'spotlight', 'data' => [
                    'heading' => 'In their *words*',
                    'testimonials' => [
                        ['quote' => 'Replace this with a real review about a set you painted.', 'author' => 'A happy client', 'role' => '{city}'],
                    ],
                ]],
                ['type' => 'gallery', 'variant' => 'mosaic', 'data' => [
                    'heading' => 'From the *chair*',
                    'images' => [
                        ['alt' => 'Replace with a photo from the studio'],
                        ['alt' => 'Replace with a photo from the studio'],
                        ['alt' => 'Replace with a photo from the studio'],
                        ['alt' => 'Replace with a photo from the studio'],
                    ],
                    'image_query' => 'manicure nails pastel',
                ]],
            ],
            draftSlug: '/work',
        );
    }
}
