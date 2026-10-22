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
use App\Themes\Scheme;
use App\Themes\ThemeBlock;
use App\Themes\ThemeDefinition;

/**
 * The transitional theme: the block set, variants, schemes and stylesheet the
 * product shipped with before themes existed, restated as a theme definition.
 *
 * Every map below is a verbatim copy of what the block classes used to declare
 * in their static `$variants` / `$variantAxes` / `$imagelessVariants`, and of
 * what the global style-preset enum used to bundle per case — comments
 * included. Do not design here — a change to how the default theme looks is a
 * change to what the old product looked like, and the point of this class is
 * that it does not.
 *
 * The scheme slugs are constants so that a template definition or a test can
 * name one without a magic string; they are the values `businesses.design_tokens`
 * stores under `scheme`, so renaming one is a data change.
 */
final readonly class DefaultTheme
{
    public const string WARM_CRAFT = 'warm-craft';

    public const string PROFESSIONAL_MINIMAL = 'professional-minimal';

    public const string FRESH_MODERN = 'fresh-modern';

    public const string BOLD_EDITORIAL = 'bold-editorial';

    public const string CALM_COASTAL = 'calm-coastal';

    public const string PLAYFUL_FRIENDLY = 'playful-friendly';

    public const string NIGHT_LOUNGE = 'night-lounge';

    public const string QUIET_LUXE = 'quiet-luxe';

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
                        // Narrow and centred like the minimal hero, but at the
                        // SMALLEST vertical step, which looks backwards for the
                        // tallest layout in the library and is the reason it
                        // works: this variant takes its height from its own
                        // min-height and centres inside it, so section padding
                        // does not add grandeur, it adds offset — the tallest
                        // step pushed the call to action clean out of a small
                        // laptop window. What is left is a guard for windows too
                        // short to centre in at all.
                        'full-viewport-quiet' => ['spacing' => 'flush'],
                    ],
                    // The type-only opening is the whole argument of the layout,
                    // and a stock photograph is what the draft pipeline would
                    // otherwise put there without being asked.
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
                    // `simple` first: every offerings block stored before
                    // variants existed has no variant key, and the default
                    // (first declared) is what keeps them rendering as they did.
                    variants: [
                        'simple' => 'Simple — plain priced list or cards',
                        'menu-card' => 'Menu card — the whole menu as one framed card, with course tabs',
                    ],
                    // The menu card is always a two-up printed menu inside its
                    // frame: rows, not cards (the frame is the card), wide
                    // because the card supplies its own measure, centred because
                    // that is how a menu is set.
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
                        // A dated list is a column of rows, not a grid, and it
                        // reads better narrow and left-aligned — a noticeboard.
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
            stylesheet: 'resources/themes/default/theme.css',
            schemes: self::schemes(),
        );
    }

    /**
     * The eight curated looks, in the order the design panel and the marketing
     * site list them.
     *
     * Each scheme bundles EIGHT token axes (palette, fonts, type style, radius,
     * density, dividers, accent surface, motion) rather than the original four —
     * the type style, seam shape and accent treatment are where two schemes
     * that share a hue stop looking like the same template, and motion is where
     * one of them stops being a static page.
     *
     * `blockVariantDefaults` is the layout variant each block type should
     * default to under the scheme — consumed by the AI draft generator when
     * stamping variants. Every key/value is shape-tested against this theme's
     * blocks. `offerings` splits on formality, not industry: the framed
     * menu-card goes to the three schemes whose businesses hand you a printed
     * menu — WarmCraft (the tea house and its kin), NightLounge (the tasting
     * menu, the cocktail list) and QuietLuxe (the omakase counter, the salon's
     * service menu). The other five sell from a list, and a frame around it
     * would be chrome against their point — restraint, SaaS convention,
     * editorial flatness, clinical calm, counter-service casualness.
     *
     * `blockAppearanceDefaults` is the background and vertical rhythm each
     * block type should carry under the scheme — the per-SECTION half of a
     * scheme, where the variant map is the per-LAYOUT half. This is what makes
     * the schemes look like different WEBSITES rather than one website in
     * different colours. Tokens explain maybe a quarter of the difference
     * between two designs (PLAN.md says as much) and layout variants most of
     * the rest, but until a scheme could say "photographs go on black here, and
     * every section breathes there", every scheme produced the same flat stack
     * of bands in a different hue.
     *
     * Deliberately NOT total, and that is the opposite of the rule for
     * variants: a missing variant makes every scheme fall back to the type's
     * first layout, so `ThemeTest` requires all of them. A missing APPEARANCE
     * falls back to the value the block's own view declares — a considered
     * default, not a degradation — so listing a type here means "this scheme
     * overrides the layout's instinct", and most types under most schemes
     * should not.
     *
     * Two types are absent from every scheme, by design and pinned by test:
     *  - `hero`, because its variant already decides its weight: the scheme
     *    that wants a dramatic opening picks `full-bleed-overlay`, which is dark
     *    by construction. A tone here would fight the variant it was chosen with.
     *  - `heading`, because it is a divider inside the page's flow rather than a
     *    band of its own. Giving it a background turns a section title into a
     *    section.
     *
     * KNOWN LIMIT — appearances are per TYPE, not per POSITION. On the RESTYLE
     * path (`StampSchemeDefaults::stamp()`) two `features` blocks on one page
     * still get the same background; the DRAFT path routes through
     * `StampSchemeDefaults::fill()`, which walks the list in order and flips a
     * repeating base/muted fallback, so a fresh generation no longer produces
     * the flat stack. The per-view defaults themselves are a queryable contract
     * (`BlockType::axisDefault()`), which is what `fill()` seeds its
     * alternation state from.
     *
     * Appearance values may name any layout axis the type declares
     * ({@see \App\Site\Blocks\LayoutAxis}), not only tone and spacing — the
     * entries that carry `columns`/`item_style`/`width`/`align`/`image_shape`
     * opinions are how each scheme keeps the looks the retired variants used to
     * provide. Write each entry's axes in `LayoutAxis` declaration order:
     * `SectionLayout::store()` re-emits stored axes in that order, so an entry
     * written out of order round-trips into a different array than the one
     * declared here, and `StampSchemeDefaultsTest` compares with `toBe()`.
     *
     * `synonyms` are the adjectives an operator actually reaches for when they
     * want this look — the words "make it more premium" has to land on.
     * Deliberately separate from `vibes`, which are INDUSTRY nouns matched
     * against the business category when composing a first draft. Not one
     * list: nothing in the vibes contains *premium*, *refined* or *sleek*, so
     * before the synonyms existed the chat assistant had nothing to ground a
     * feeling against and would free-associate among the labels. Published
     * verbatim in the chat prompt and in {@see \App\Ai\Tools\SetSiteStyle}'s
     * schema description, which is the whole mechanism: a brand reference
     * ("like Apple") is translated into adjectives and matched here, so no
     * brand name ever reaches storage. Keep the lists disjoint — an adjective
     * that names two schemes grounds neither, and `ThemeTest` asserts it.
     *
     * @return non-empty-array<string, Scheme>
     */
    private static function schemes(): array
    {
        return [
            self::WARM_CRAFT => new Scheme(
                slug: self::WARM_CRAFT,
                label: 'Warm craft',
                description: 'Earthy tones, elegant serifs and generous spacing — for artisan and hospitality businesses.',
                tokens: new DesignTokens(self::WARM_CRAFT, ColorPalette::WarmSand, FontPair::WarmEditorial, RadiusScale::Lg, SpacingDensity::Spacious, typeStyle: TypeStyle::Refined, divider: SectionDivider::Curve, accent: AccentStyle::Flat),
                blockVariantDefaults: ['hero' => 'left-text-right-image', 'features' => 'alternating', 'offerings' => 'menu-card', 'testimonials' => 'grid', 'gallery' => 'masonry', 'cta' => 'banner', 'header' => 'centered', 'footer' => 'columns', 'prose' => 'side-heading', 'signup' => 'banner', 'updates' => 'cards'],
                // Earthy and unhurried: shaded bands rather than dark ones, because
                // near-black reads as cold against warm sand, and the generous
                // spacing token wants generous sections to match.
                blockAppearanceDefaults: [
                    'features' => ['tone' => 'muted', 'spacing' => 'airy'],
                    // No width/align opinion: the default variant here is the framed
                    // menu-card, which supplies its own measure and sets centred —
                    // an appearance entry is variant-blind, so pinning `start` for
                    // the priced list would also skew the frame.
                    'offerings' => ['tone' => 'base', 'spacing' => 'airy', 'columns' => 'two', 'item_style' => 'plain'],
                    'gallery' => ['tone' => 'base', 'spacing' => 'airy', 'width' => 'full'],
                    'visit' => ['tone' => 'muted'],
                    'testimonials' => ['tone' => 'muted'],
                    'prose' => ['spacing' => 'airy'],
                    'faq' => ['tone' => 'muted'],
                    'contact' => ['tone' => 'muted', 'spacing' => 'airy'],
                    'cta' => ['tone' => 'muted', 'item_style' => 'card'],
                ],
                synonyms: ['warm', 'welcoming', 'homely', 'rustic', 'organic', 'inviting'],
                vibes: ['warm', 'handmade', 'artisan', 'bakery', 'spa', 'craft'],
            ),
            self::PROFESSIONAL_MINIMAL => new Scheme(
                slug: self::PROFESSIONAL_MINIMAL,
                label: 'Professional minimal',
                description: 'Monochrome, tight and typographic — for services that sell trust.',
                tokens: new DesignTokens(self::PROFESSIONAL_MINIMAL, ColorPalette::Charcoal, FontPair::NeoGrotesque, RadiusScale::Sm, SpacingDensity::Normal, typeStyle: TypeStyle::Quiet, divider: SectionDivider::None, accent: AccentStyle::Flat),
                blockVariantDefaults: ['hero' => 'centered-minimal', 'features' => 'rows', 'offerings' => 'simple', 'testimonials' => 'grid', 'gallery' => 'grid', 'cta' => 'banner', 'header' => 'simple', 'footer' => 'minimal', 'prose' => 'stacked', 'signup' => 'stacked', 'updates' => 'list'],
                // Sells trust, so it spends nothing on decoration: almost every
                // section sits on the page background, including testimonials, whose
                // view shades itself by default. The restraint IS the look.
                blockAppearanceDefaults: [
                    'features' => ['tone' => 'base'],
                    'offerings' => ['tone' => 'base', 'width' => 'narrow', 'align' => 'start', 'columns' => 'one', 'item_style' => 'plain'],
                    'gallery' => ['tone' => 'base'],
                    'visit' => ['tone' => 'base'],
                    'testimonials' => ['tone' => 'base'],
                    'faq' => ['tone' => 'base'],
                    'contact' => ['tone' => 'base'],
                ],
                synonyms: ['premium', 'refined', 'upmarket', 'sleek', 'minimal', 'understated', 'serious', 'expensive'],
                vibes: ['professional', 'corporate', 'law', 'finance', 'consulting', 'clean'],
            ),
            self::FRESH_MODERN => new Scheme(
                slug: self::FRESH_MODERN,
                label: 'Fresh modern',
                description: 'Green-tinted, geometric and energetic — for modern brands.',
                tokens: new DesignTokens(self::FRESH_MODERN, ColorPalette::Forest, FontPair::Contemporary, RadiusScale::Md, SpacingDensity::Normal, typeStyle: TypeStyle::Classic, divider: SectionDivider::None, accent: AccentStyle::Gradient),
                blockVariantDefaults: ['hero' => 'left-text-right-image', 'features' => 'grid', 'offerings' => 'simple', 'testimonials' => 'carousel', 'gallery' => 'grid', 'cta' => 'banner', 'header' => 'simple', 'footer' => 'columns', 'prose' => 'stacked', 'signup' => 'banner', 'updates' => 'cards'],
                // Alternating shade gives it pace without weight — the closest in the
                // library to a conventional SaaS page.
                blockAppearanceDefaults: [
                    'features' => ['tone' => 'muted'],
                    'offerings' => ['tone' => 'base'],
                    'gallery' => ['tone' => 'muted'],
                    'visit' => ['tone' => 'base'],
                    'testimonials' => ['tone' => 'base'],
                    'faq' => ['tone' => 'muted', 'width' => 'wide', 'align' => 'center', 'columns' => 'two'],
                    'team' => ['item_style' => 'card', 'image_shape' => 'portrait'],
                    'contact' => ['tone' => 'base'],
                ],
                synonyms: ['modern', 'fresh', 'clean', 'crisp', 'contemporary', 'techy'],
                vibes: ['fresh', 'startup', 'tech', 'modern', 'saas'],
            ),
            self::BOLD_EDITORIAL => new Scheme(
                slug: self::BOLD_EDITORIAL,
                label: 'Bold editorial',
                description: 'High-contrast plum, sharp corners and magazine typography.',
                tokens: new DesignTokens(self::BOLD_EDITORIAL, ColorPalette::Plum, FontPair::Signage, RadiusScale::None, SpacingDensity::Compact, typeStyle: TypeStyle::Impact, divider: SectionDivider::Slant, accent: AccentStyle::Flat),
                blockVariantDefaults: ['hero' => 'full-bleed-overlay', 'features' => 'grid', 'offerings' => 'simple', 'testimonials' => 'spotlight', 'gallery' => 'masonry', 'cta' => 'banner', 'header' => 'centered', 'footer' => 'minimal', 'prose' => 'side-heading', 'signup' => 'stacked', 'updates' => 'cards'],
                // The one scheme that uses the dark tone as a design element:
                // photographs and quotes go on black, and the compact spacing token
                // is echoed section by section. Magazine, not brochure.
                blockAppearanceDefaults: [
                    'gallery' => ['tone' => 'inverted', 'spacing' => 'tight', 'width' => 'full'],
                    'visit' => ['tone' => 'base', 'spacing' => 'tight'],
                    'testimonials' => ['tone' => 'inverted'],
                    'features' => ['tone' => 'base', 'spacing' => 'tight', 'width' => 'narrow', 'align' => 'start', 'columns' => 'one', 'item_style' => 'plain'],
                    'offerings' => ['tone' => 'muted', 'spacing' => 'tight'],
                    'prose' => ['spacing' => 'tight'],
                    'faq' => ['tone' => 'base', 'spacing' => 'tight', 'width' => 'wide', 'align' => 'center', 'columns' => 'two'],
                    'team' => ['item_style' => 'card', 'image_shape' => 'portrait'],
                    'contact' => ['tone' => 'inverted', 'width' => 'narrow', 'align' => 'center', 'columns' => 'one'],
                ],
                synonyms: ['bold', 'dramatic', 'high-contrast', 'striking', 'confident', 'edgy'],
                vibes: ['bold', 'fashion', 'studio', 'photography', 'editorial'],
            ),
            self::CALM_COASTAL => new Scheme(
                slug: self::CALM_COASTAL,
                label: 'Calm coastal',
                description: 'Cool blues, soft corners and airy spacing — for wellness and care.',
                tokens: new DesignTokens(self::CALM_COASTAL, ColorPalette::Ocean, FontPair::QuietSerif, RadiusScale::Lg, SpacingDensity::Spacious, typeStyle: TypeStyle::Refined, divider: SectionDivider::Curve, accent: AccentStyle::Sheen),
                blockVariantDefaults: ['hero' => 'centered-minimal', 'features' => 'grid', 'offerings' => 'simple', 'testimonials' => 'carousel', 'gallery' => 'grid', 'cta' => 'banner', 'header' => 'simple', 'footer' => 'columns', 'prose' => 'stacked', 'signup' => 'stacked', 'updates' => 'list'],
                // Air is the whole point: every content section gets the roomiest
                // step, and nothing is ever dark — wellness and care do not shout.
                blockAppearanceDefaults: [
                    'features' => ['tone' => 'base', 'spacing' => 'airy'],
                    'offerings' => ['tone' => 'muted', 'spacing' => 'airy', 'width' => 'narrow', 'align' => 'start', 'columns' => 'one', 'item_style' => 'plain'],
                    'visit' => ['tone' => 'base', 'spacing' => 'airy'],
                    'cta' => ['item_style' => 'card'],
                    'gallery' => ['tone' => 'base', 'spacing' => 'airy'],
                    'testimonials' => ['tone' => 'muted', 'spacing' => 'airy'],
                    'prose' => ['spacing' => 'airy'],
                    'faq' => ['tone' => 'base', 'spacing' => 'airy'],
                    'contact' => ['tone' => 'muted', 'spacing' => 'airy', 'width' => 'narrow', 'align' => 'center', 'columns' => 'one'],
                ],
                synonyms: ['calm', 'airy', 'gentle', 'soothing', 'light', 'spacious'],
                vibes: ['calm', 'wellness', 'clinic', 'dental', 'coastal', 'yoga'],
            ),
            self::PLAYFUL_FRIENDLY => new Scheme(
                slug: self::PLAYFUL_FRIENDLY,
                label: 'Playful friendly',
                description: 'Sunset colors, round shapes and a friendly voice.',
                tokens: new DesignTokens(self::PLAYFUL_FRIENDLY, ColorPalette::Sunset, FontPair::Soft, RadiusScale::Full, SpacingDensity::Normal, typeStyle: TypeStyle::Friendly, divider: SectionDivider::Peak, accent: AccentStyle::Gradient),
                blockVariantDefaults: ['hero' => 'full-bleed-overlay', 'features' => 'grid', 'offerings' => 'simple', 'testimonials' => 'carousel', 'gallery' => 'filmstrip', 'cta' => 'banner', 'header' => 'centered', 'footer' => 'columns', 'prose' => 'stacked', 'signup' => 'banner', 'updates' => 'cards'],
                // Colour-forward and busy on purpose: the brand tone shows up on the
                // call to action, and shaded bands keep the page lively.
                blockAppearanceDefaults: [
                    'features' => ['tone' => 'muted'],
                    'offerings' => ['tone' => 'base', 'align' => 'start', 'columns' => 'two'],
                    'gallery' => ['tone' => 'muted', 'width' => 'full'],
                    'visit' => ['tone' => 'muted'],
                    'testimonials' => ['tone' => 'muted'],
                    'faq' => ['tone' => 'base', 'width' => 'wide', 'align' => 'center', 'columns' => 'two'],
                    'contact' => ['tone' => 'muted', 'width' => 'narrow', 'align' => 'center', 'columns' => 'one'],
                    'cta' => ['tone' => 'accent'],
                    // Square, not portrait: with the Full radius token the crop
                    // reads as a sticker — the playful cousin of the studio card.
                    'team' => ['item_style' => 'card', 'image_shape' => 'square'],
                ],
                synonyms: ['playful', 'friendly', 'fun', 'approachable', 'colourful', 'casual'],
                vibes: ['playful', 'family', 'kids', 'pets', 'fun', 'casual'],
            ),
            self::NIGHT_LOUNGE => new Scheme(
                slug: self::NIGHT_LOUNGE,
                label: 'Night lounge',
                description: 'Near-black and gold, heavy type on a dark room — the one dark-site scheme.',
                tokens: new DesignTokens(self::NIGHT_LOUNGE, ColorPalette::NoirGold, FontPair::HighContrast, RadiusScale::Sm, SpacingDensity::Normal, typeStyle: TypeStyle::Impact, divider: SectionDivider::Slant, accent: AccentStyle::Sheen),
                blockVariantDefaults: ['hero' => 'full-bleed-overlay', 'features' => 'alternating', 'offerings' => 'menu-card', 'testimonials' => 'spotlight', 'gallery' => 'masonry', 'cta' => 'banner', 'header' => 'inverted', 'footer' => 'minimal', 'prose' => 'side-heading', 'signup' => 'banner', 'updates' => 'cards'],
                // The whole site is already dark (NoirGold base), so this scheme
                // never reaches for `inverted` — BoldEditorial keeps sole ownership
                // of that tone. Rhythm here is raised panels (`muted` is LIGHTER
                // than the base on a dark ramp) and one gold accent moment.
                blockAppearanceDefaults: [
                    'features' => ['tone' => 'base', 'spacing' => 'tight'],
                    // No align opinion, same reason as WarmCraft: the menu-card
                    // default sets its own centring, and a raised muted panel is
                    // all this band needs.
                    'offerings' => ['tone' => 'muted', 'columns' => 'two'],
                    'gallery' => ['tone' => 'base', 'spacing' => 'tight', 'width' => 'full'],
                    'visit' => ['tone' => 'muted', 'spacing' => 'tight'],
                    'testimonials' => ['tone' => 'muted', 'spacing' => 'airy'],
                    'faq' => ['tone' => 'muted'],
                    'team' => ['item_style' => 'card', 'image_shape' => 'portrait'],
                    'contact' => ['tone' => 'base', 'width' => 'narrow', 'align' => 'center', 'columns' => 'one'],
                    'cta' => ['tone' => 'accent', 'spacing' => 'tight'],
                ],
                synonyms: ['dark', 'moody', 'luxe', 'glamorous', 'nocturnal'],
                vibes: ['bar', 'nightlife', 'barber', 'tattoo', 'salon', 'music'],
            ),
            self::QUIET_LUXE => new Scheme(
                slug: self::QUIET_LUXE,
                label: 'Quiet luxe',
                description: 'Warm stone, hairline serifs set very large, square corners and sections that arrive as you scroll.',
                // The only scheme that asks for motion, and the only one with a
                // sub-500 display weight — the two go together. A high-contrast
                // serif at this size wants to arrive rather than to be already
                // there, and the pairing with WarmEditorial is enforced
                // (FontPairTest), because Newsreader is the only heading face here
                // that ships a real 300; any other snaps it back up to 400 or 500
                // and loses the whole look.
                tokens: new DesignTokens(self::QUIET_LUXE, ColorPalette::Stone, FontPair::WarmEditorial, RadiusScale::None, SpacingDensity::Spacious, typeStyle: TypeStyle::Serene, divider: SectionDivider::None, accent: AccentStyle::Flat, motion: MotionStyle::Reveal),
                // The one scheme that opens with the type-only hero: everything else
                // here is the quietest layout its type offers, and a photograph in
                // the first viewport would be the loudest thing on the site.
                blockVariantDefaults: ['hero' => 'full-viewport-quiet', 'features' => 'rows', 'offerings' => 'menu-card', 'testimonials' => 'spotlight', 'gallery' => 'grid', 'cta' => 'banner', 'header' => 'simple', 'footer' => 'minimal', 'prose' => 'stacked', 'signup' => 'stacked', 'updates' => 'list'],
                // Air and narrow measures everywhere, and the only scheme that never
                // reaches for `accent`: its palette has no accent HUE to spend (see
                // ColorPalette::Stone), so a brand-coloured band would be a slightly
                // different grey pretending to be an event. The closing CTA earns its
                // emphasis from the shade step and the whitespace around it instead.
                //
                // Two columns wherever a type offers three, for the same reason: at
                // this display scale a three-across row of anything is a wall.
                blockAppearanceDefaults: [
                    'features' => ['tone' => 'base', 'spacing' => 'airy', 'width' => 'narrow', 'align' => 'center', 'columns' => 'two', 'item_style' => 'plain'],
                    'offerings' => ['tone' => 'muted', 'spacing' => 'airy', 'align' => 'center', 'columns' => 'two', 'item_style' => 'plain'],
                    'visit' => ['tone' => 'base', 'spacing' => 'airy', 'align' => 'center'],
                    'prose' => ['tone' => 'muted', 'spacing' => 'airy', 'align' => 'center'],
                    'gallery' => ['tone' => 'base', 'spacing' => 'airy', 'columns' => 'two', 'image_shape' => 'portrait'],
                    'testimonials' => ['tone' => 'base', 'spacing' => 'airy', 'width' => 'narrow', 'align' => 'center', 'columns' => 'one', 'item_style' => 'plain'],
                    'faq' => ['tone' => 'muted', 'spacing' => 'airy', 'align' => 'center'],
                    'contact' => ['tone' => 'base', 'spacing' => 'airy', 'width' => 'narrow', 'align' => 'center', 'columns' => 'one'],
                    'cta' => ['tone' => 'muted', 'spacing' => 'airy', 'item_style' => 'plain'],
                    // The one type that reaches for `accent` on its own, brought
                    // back in line: this scheme spends no brand band anywhere, and
                    // on a monochrome palette the accent surface is the near-black
                    // primary — a black slab two thirds down a page made of paper.
                    'signup' => ['tone' => 'muted', 'spacing' => 'airy', 'align' => 'center'],
                    'team' => ['item_style' => 'plain', 'image_shape' => 'portrait'],
                ],
                synonyms: ['quiet', 'serene', 'delicate', 'elegant', 'tranquil', 'unhurried', 'poised'],
                vibes: ['hair', 'stylist', 'beauty', 'skincare', 'boutique', 'atelier'],
            ),
        ];
    }
}
