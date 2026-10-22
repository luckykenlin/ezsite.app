<?php

declare(strict_types=1);

namespace App\Themes\Definitions;

use App\Design\AccentStyle;
use App\Design\ColorPalette;
use App\Design\DesignTokens;
use App\Design\FontPair;
use App\Design\MotionStyle;
use App\Design\RadiusScale;
use App\Design\SectionDivider;
use App\Design\SpacingDensity;
use App\Design\TypeStyle;
use App\Themes\Definitions\NailLuxe\Pages\Artists;
use App\Themes\Definitions\NailLuxe\Pages\Contact;
use App\Themes\Definitions\NailLuxe\Pages\Home;
use App\Themes\Definitions\NailLuxe\Pages\Services;
use App\Themes\Definitions\NailLuxe\Pages\Work;
use App\Themes\Scheme;
use App\Themes\ThemeBlock;
use App\Themes\ThemeDefinition;
use App\Themes\ThemePage;

/**
 * The first vertical theme: a boutique nail studio.
 *
 * Designed, not ported. Every layout here was drawn for a studio that sells
 * an appointment at a lamp — large serif headlines with one phrase set in
 * italic (`*like this*` in a heading field, see the `x-site.emphasis`
 * component), a small sparkle ornament over each section title, tall arched
 * photo frames, pill buttons, a price list set like a printed card with
 * hairline rules, and a dark rounded footer card inset from the page. The
 * rejected alternative was the default theme recoloured, which is what every
 * generated salon site already looks like.
 *
 * The block list is deliberately short. `features`, `signup`, `reservation`
 * and `updates` are absent in v1: a studio's reasons-to-choose live in
 * `credentials` and its prices, it books by phone or form rather than a table
 * reservation, and the update feed has no nail layout yet — a type listed
 * here without a designed view would render the default theme's look inside
 * this one. `credentials` is this theme's own block.
 *
 * Scheme slugs are constants for the same reason {@see DefaultTheme}'s are:
 * they are what `businesses.design_tokens` stores under `scheme`.
 */
final readonly class NailLuxe
{
    public const string BLUSH_PLUM = 'blush-plum';

    public const string CREAM_SAGE = 'cream-sage';

    public const string IVORY_NOIR = 'ivory-noir';

    public static function definition(): ThemeDefinition
    {
        return new ThemeDefinition(
            label: 'Nail Luxe',
            blocks: [
                'header' => new ThemeBlock(
                    variants: [
                        'studio' => 'Studio — an info strip with hours and phone above the navigation',
                        'centered' => 'Centered — the name above centred links',
                    ],
                ),
                'footer' => new ThemeBlock(
                    variants: [
                        'card' => 'A dark rounded card inset from the page edges',
                    ],
                ),
                'hero' => new ThemeBlock(
                    variants: [
                        'arch' => 'Headline left, one tall arched photo right',
                        'editorial' => 'Centered type-only headline with an ornament',
                    ],
                    variantAxes: [
                        'arch' => ['spacing' => 'normal', 'width' => 'wide', 'align' => 'start'],
                        'editorial' => ['spacing' => 'airy', 'width' => 'normal', 'align' => 'center'],
                    ],
                    // The editorial opening is type on blush and nothing else;
                    // a stock photograph there is what the draft would add
                    // unasked.
                    imagelessVariants: ['editorial'],
                ),
                'heading' => new ThemeBlock,
                'prose' => new ThemeBlock(
                    variants: [
                        'centered' => 'Ornament, serif heading and the text centred below',
                    ],
                    variantAxes: [
                        'centered' => ['align' => 'center'],
                    ],
                ),
                'offerings' => new ThemeBlock(
                    // The price list first: it is what a services page is,
                    // and an offerings block stored without a variant should
                    // land on it.
                    variants: [
                        'price-list' => 'Price list — names and prices on hairline rules, grouped by category',
                        'category-cards' => 'Category cards — one card per group with a count and a link',
                    ],
                    variantAxes: [
                        'price-list' => ['width' => 'normal', 'align' => 'center', 'columns' => 'two', 'item_style' => 'plain'],
                        'category-cards' => ['columns' => 'three', 'item_style' => 'card'],
                    ],
                    imagelessVariants: ['price-list'],
                ),
                'gallery' => new ThemeBlock(
                    variants: [
                        'arch-grid' => 'Arched tiles in a grid',
                        'mosaic' => 'A mosaic with one tall feature photo',
                    ],
                ),
                'team' => new ThemeBlock,
                'testimonials' => new ThemeBlock(
                    variants: [
                        'cards' => 'Quote cards',
                        'spotlight' => 'One quote, large and centred',
                    ],
                    variantAxes: [
                        'spotlight' => ['width' => 'narrow', 'columns' => 'one', 'item_style' => 'plain'],
                    ],
                ),
                'faq' => new ThemeBlock,
                'visit' => new ThemeBlock,
                'contact' => new ThemeBlock,
                'cta' => new ThemeBlock(
                    variants: [
                        'offer' => 'A photograph band with the offer on a rounded card',
                    ],
                    variantAxes: [
                        'offer' => ['width' => 'wide'],
                    ],
                ),
                'credentials' => new ThemeBlock,
            ],
            stylesheet: 'resources/themes/nail-luxe/theme.css',
            schemes: self::schemes(),
            pages: self::pages(),
        );
    }

    /**
     * The "Add a page" picker's designed pages: the landing page and the four
     * supporting pages a studio site has. Every supporting page carries a
     * draft slug, so the AI site draft may write all four.
     *
     * @return array<string, ThemePage>
     */
    private static function pages(): array
    {
        $pages = [];

        foreach ([Home::definition(), Services::definition(), Artists::definition(), Work::definition(), Contact::definition()] as $page) {
            $pages[$page->key] = $page;
        }

        return $pages;
    }

    /**
     * Three looks for one studio, not three studios: every scheme keeps the
     * arches, the ornament and the italic emphasis, and differs in what a
     * client would describe as the room — blush walls and plum velvet, cream
     * linen and potted eucalyptus, or ivory and black lacquer.
     *
     * Rhythm alternates page-background and shaded bands down a home page
     * (hero, credentials, services, work, words, questions, offer), so each
     * appearance map assigns the shade to alternate types; no scheme uses the
     * dark tone, because the footer card is the one dark surface the theme
     * spends. The serif display face is always set at its regular weight by
     * the stylesheet, so the type style contributes scale, tracking and the
     * eyebrow voice rather than weight.
     *
     * @return non-empty-array<string, Scheme>
     */
    private static function schemes(): array
    {
        return [
            self::BLUSH_PLUM => new Scheme(
                slug: self::BLUSH_PLUM,
                label: 'Blush & plum',
                description: 'Blush walls, deep plum ink and a condensed serif — the signature boutique studio.',
                // The one scheme that moves: sections settle in as you scroll,
                // which suits the slow, appointment-only register of the look.
                tokens: new DesignTokens(self::BLUSH_PLUM, ColorPalette::Blush, FontPair::QuietSerif, RadiusScale::Lg, SpacingDensity::Spacious, typeStyle: TypeStyle::Refined, divider: SectionDivider::None, accent: AccentStyle::Flat, motion: MotionStyle::Reveal),
                blockVariantDefaults: ['header' => 'studio', 'footer' => 'card', 'hero' => 'arch', 'prose' => 'centered', 'offerings' => 'price-list', 'gallery' => 'arch-grid', 'testimonials' => 'cards', 'cta' => 'offer'],
                blockAppearanceDefaults: [
                    'credentials' => ['tone' => 'muted', 'spacing' => 'airy'],
                    'offerings' => ['tone' => 'base', 'spacing' => 'airy'],
                    'gallery' => ['tone' => 'muted'],
                    'testimonials' => ['tone' => 'base'],
                    'faq' => ['tone' => 'muted', 'width' => 'normal'],
                    'visit' => ['tone' => 'muted'],
                    'contact' => ['tone' => 'base', 'spacing' => 'airy'],
                    'team' => ['tone' => 'base'],
                    'cta' => ['tone' => 'base'],
                ],
                synonyms: ['romantic', 'pretty', 'feminine', 'blush', 'boutique', 'soft'],
                vibes: ['nail', 'manicure', 'nail salon', 'nail studio', 'beauty'],
            ),
            self::CREAM_SAGE => new Scheme(
                slug: self::CREAM_SAGE,
                label: 'Cream & sage',
                description: 'Cream linen, sage green and a warm editorial serif — the natural, non-toxic studio.',
                tokens: new DesignTokens(self::CREAM_SAGE, ColorPalette::Sage, FontPair::WarmEditorial, RadiusScale::Full, SpacingDensity::Spacious, typeStyle: TypeStyle::Classic, divider: SectionDivider::None, accent: AccentStyle::Flat),
                blockVariantDefaults: ['header' => 'centered', 'footer' => 'card', 'hero' => 'editorial', 'prose' => 'centered', 'offerings' => 'price-list', 'gallery' => 'mosaic', 'testimonials' => 'cards', 'cta' => 'offer'],
                blockAppearanceDefaults: [
                    'credentials' => ['tone' => 'base', 'spacing' => 'airy'],
                    'offerings' => ['tone' => 'muted', 'spacing' => 'airy'],
                    'gallery' => ['tone' => 'base'],
                    'testimonials' => ['tone' => 'muted'],
                    'faq' => ['tone' => 'base', 'width' => 'normal'],
                    'visit' => ['tone' => 'base'],
                    'contact' => ['tone' => 'muted', 'spacing' => 'airy'],
                    'team' => ['tone' => 'muted'],
                    'cta' => ['tone' => 'muted'],
                ],
                synonyms: ['natural', 'earthy', 'botanical', 'organic', 'gentle', 'grounded'],
                vibes: ['non-toxic nails', 'natural nails', 'nail spa', 'eco salon', 'wellness'],
            ),
            self::IVORY_NOIR => new Scheme(
                slug: self::IVORY_NOIR,
                label: 'Ivory & noir',
                description: 'Ivory paper, black lacquer and a high-contrast didone — the couture nail atelier.',
                tokens: new DesignTokens(self::IVORY_NOIR, ColorPalette::Ivory, FontPair::HighContrast, RadiusScale::Lg, SpacingDensity::Spacious, typeStyle: TypeStyle::Editorial, divider: SectionDivider::None, accent: AccentStyle::Flat),
                blockVariantDefaults: ['header' => 'studio', 'footer' => 'card', 'hero' => 'arch', 'prose' => 'centered', 'offerings' => 'price-list', 'gallery' => 'mosaic', 'testimonials' => 'spotlight', 'cta' => 'offer'],
                blockAppearanceDefaults: [
                    'credentials' => ['tone' => 'base', 'spacing' => 'airy'],
                    'offerings' => ['tone' => 'muted', 'spacing' => 'airy'],
                    'gallery' => ['tone' => 'base', 'spacing' => 'airy'],
                    'testimonials' => ['tone' => 'muted', 'spacing' => 'airy'],
                    'faq' => ['tone' => 'base', 'width' => 'normal'],
                    'visit' => ['tone' => 'muted'],
                    'contact' => ['tone' => 'base', 'spacing' => 'airy'],
                    'team' => ['tone' => 'base', 'spacing' => 'airy'],
                    'cta' => ['tone' => 'base'],
                ],
                synonyms: ['luxurious', 'couture', 'chic', 'glamorous', 'polished', 'high-end'],
                vibes: ['nail atelier', 'luxury nails', 'nail art', 'nail bar', 'lash and brow'],
            ),
        ];
    }
}
