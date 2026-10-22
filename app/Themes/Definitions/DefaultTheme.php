<?php

declare(strict_types=1);

namespace App\Themes\Definitions;

use App\Themes\ThemeBlock;
use App\Themes\ThemeDefinition;

/**
 * The transitional theme: the block set, variants and stylesheet the product
 * shipped with before themes existed, restated as a theme definition.
 *
 * Every map below is a verbatim copy of what the block classes declared in
 * their static `$variants` / `$variantAxes` / `$imagelessVariants`; while
 * both exist, `DefaultThemeTest` holds them in parity, and the statics are
 * removed in the slice that makes the block classes read from the theme. Do
 * not design here — a change to how the default theme looks is a change to
 * what the old product looked like, and the point of this class is that it
 * does not.
 */
final readonly class DefaultTheme
{
    public static function definition(): ThemeDefinition
    {
        return new ThemeDefinition(
            label: 'Default',
            blocks: [
                'header' => new ThemeBlock(
                    variants: [
                        'simple' => 'Brand left, links right',
                        'centered' => 'Brand above centered links',
                        'inverted' => 'Dark',
                    ],
                ),
                'footer' => new ThemeBlock(
                    variants: [
                        'columns' => 'Brand, links and contact columns',
                        'minimal' => 'Single centered row',
                        'soft' => 'Soft light',
                    ],
                ),
                'hero' => new ThemeBlock(
                    variants: [
                        'centered-minimal' => 'Centered, minimal',
                        'left-text-right-image' => 'Left text, right image',
                        'full-bleed-overlay' => 'Full-bleed image with overlay',
                        'full-viewport-quiet' => 'Full viewport, type only — a quiet, unhurried opening',
                    ],
                    variantAxes: [
                        'left-text-right-image' => ['spacing' => 'normal', 'width' => 'wide', 'align' => 'start'],
                        'full-bleed-overlay' => ['tone' => 'inverted', 'spacing' => 'tall', 'width' => 'normal', 'align' => 'start'],
                        'full-viewport-quiet' => ['spacing' => 'flush'],
                    ],
                    imagelessVariants: ['full-viewport-quiet'],
                ),
                'heading' => new ThemeBlock,
                'prose' => new ThemeBlock(
                    variants: [
                        'stacked' => 'Heading above the text',
                        'side-heading' => 'Heading beside the text',
                    ],
                    variantAxes: [
                        'side-heading' => ['width' => 'wide'],
                    ],
                ),
                'features' => new ThemeBlock(
                    variants: [
                        'grid' => 'Three-column cards',
                        'alternating' => 'Alternating rows',
                        'rows' => 'Text rows',
                    ],
                    variantAxes: [
                        'alternating' => ['item_style' => 'plain', 'image_shape' => 'standard'],
                        'rows' => ['width' => 'normal', 'columns' => 'two', 'item_style' => 'plain'],
                    ],
                ),
                'offerings' => new ThemeBlock(
                    variants: [
                        'simple' => 'Simple — plain priced list or cards',
                        'menu-card' => 'Menu card — the whole menu as one framed card, with course tabs',
                    ],
                    variantAxes: [
                        'menu-card' => ['spacing' => 'airy', 'width' => 'wide', 'align' => 'center', 'columns' => 'two', 'item_style' => 'plain'],
                    ],
                ),
                'gallery' => new ThemeBlock(
                    variants: [
                        'grid' => 'Uniform grid',
                        'masonry' => 'Masonry columns',
                        'filmstrip' => 'Filmstrip',
                    ],
                    variantAxes: [
                        'filmstrip' => ['image_shape' => 'standard'],
                    ],
                ),
                'team' => new ThemeBlock,
                'testimonials' => new ThemeBlock(
                    variants: [
                        'grid' => 'Two-column cards',
                        'carousel' => 'Horizontal carousel',
                        'spotlight' => 'Spotlight quote',
                    ],
                    variantAxes: [
                        'spotlight' => ['width' => 'narrow', 'columns' => 'one', 'item_style' => 'plain'],
                    ],
                ),
                'updates' => new ThemeBlock(
                    variants: [
                        'cards' => 'Cards',
                        'list' => 'Dated list',
                    ],
                    variantAxes: [
                        'list' => ['width' => 'narrow', 'align' => 'start', 'columns' => 'one', 'item_style' => 'plain'],
                    ],
                ),
                'faq' => new ThemeBlock,
                'visit' => new ThemeBlock,
                'reservation' => new ThemeBlock,
                'contact' => new ThemeBlock,
                'signup' => new ThemeBlock(
                    variants: [
                        'banner' => 'Form beside the offer',
                        'stacked' => 'Form below the offer',
                    ],
                    variantAxes: [
                        'stacked' => ['align' => 'center', 'width' => 'narrow'],
                    ],
                ),
                'cta' => new ThemeBlock(
                    variants: [
                        'banner' => 'Full-width banner',
                        'full-photo' => 'Full-bleed photo',
                    ],
                    variantAxes: [
                        'full-photo' => ['tone' => 'inverted', 'spacing' => 'tall', 'width' => 'narrow'],
                    ],
                ),
            ],
            stylesheet: 'resources/css/site.css',
        );
    }
}
