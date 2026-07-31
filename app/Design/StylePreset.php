<?php

declare(strict_types=1);

namespace App\Design;

/**
 * Curated bundles of tokens + per-block default variants, each tagged with
 * an industry vibe. Presets are the guard rail against jarring free-form
 * variant × token combinations (PLAN.md): users and the AI pick a preset,
 * then fine-tune within the enumerated token space.
 *
 * Kept as a PHP enum (not a DB table): presets are designed artifacts —
 * versioned, testable, and enumerable for the AI vocabulary via ::cases().
 */
enum StylePreset: string
{
    case WarmCraft = 'warm-craft';
    case ProfessionalMinimal = 'professional-minimal';
    case FreshModern = 'fresh-modern';
    case BoldEditorial = 'bold-editorial';
    case CalmCoastal = 'calm-coastal';
    case PlayfulFriendly = 'playful-friendly';

    public function tokens(): DesignTokens
    {
        return match ($this) {
            self::WarmCraft => new DesignTokens($this, ColorPalette::WarmSand, FontPair::ElegantSerif, RadiusScale::Lg, SpacingDensity::Spacious),
            self::ProfessionalMinimal => new DesignTokens($this, ColorPalette::Charcoal, FontPair::ModernSans, RadiusScale::Sm, SpacingDensity::Normal),
            self::FreshModern => new DesignTokens($this, ColorPalette::Forest, FontPair::Geometric, RadiusScale::Md, SpacingDensity::Normal),
            self::BoldEditorial => new DesignTokens($this, ColorPalette::Plum, FontPair::Editorial, RadiusScale::None, SpacingDensity::Compact),
            self::CalmCoastal => new DesignTokens($this, ColorPalette::Ocean, FontPair::ModernSans, RadiusScale::Lg, SpacingDensity::Spacious),
            self::PlayfulFriendly => new DesignTokens($this, ColorPalette::Sunset, FontPair::FriendlyRounded, RadiusScale::Full, SpacingDensity::Normal),
        };
    }

    /**
     * The layout variant each block type should default to under this
     * preset — consumed by the AI draft generator when stamping variants.
     * Every key/value here is shape-tested against BlockRegistry::vocabulary().
     *
     * @return array<string, string>
     */
    public function blockVariantDefaults(): array
    {
        return match ($this) {
            self::WarmCraft => ['hero' => 'left-text-right-image', 'features' => 'list', 'testimonials' => 'grid', 'gallery' => 'masonry', 'cta' => 'boxed', 'contact' => 'split', 'header' => 'centered', 'footer' => 'columns', 'offerings' => 'list'],
            self::ProfessionalMinimal => ['hero' => 'centered-minimal', 'features' => 'grid', 'testimonials' => 'grid', 'gallery' => 'grid', 'cta' => 'banner', 'contact' => 'split', 'header' => 'simple', 'footer' => 'minimal', 'offerings' => 'list'],
            self::FreshModern => ['hero' => 'left-text-right-image', 'features' => 'grid', 'testimonials' => 'carousel', 'gallery' => 'grid', 'cta' => 'banner', 'contact' => 'split', 'header' => 'simple', 'footer' => 'columns', 'offerings' => 'cards'],
            self::BoldEditorial => ['hero' => 'full-bleed-overlay', 'features' => 'list', 'testimonials' => 'grid', 'gallery' => 'masonry', 'cta' => 'banner', 'contact' => 'stacked', 'header' => 'centered', 'footer' => 'minimal', 'offerings' => 'cards'],
            self::CalmCoastal => ['hero' => 'centered-minimal', 'features' => 'grid', 'testimonials' => 'carousel', 'gallery' => 'grid', 'cta' => 'boxed', 'contact' => 'stacked', 'header' => 'simple', 'footer' => 'columns', 'offerings' => 'list'],
            self::PlayfulFriendly => ['hero' => 'full-bleed-overlay', 'features' => 'grid', 'testimonials' => 'carousel', 'gallery' => 'masonry', 'cta' => 'banner', 'contact' => 'stacked', 'header' => 'centered', 'footer' => 'columns', 'offerings' => 'cards'],
        };
    }

    /**
     * The adjectives an operator actually reaches for when they want this look
     * — the words "make it more premium" has to land on.
     *
     * Deliberately separate from {@see vibes()}, which are INDUSTRY nouns
     * matched against the business category when composing a first draft. Not
     * one list: nothing in `vibes()` contains *premium*, *refined* or *sleek*,
     * so before this existed the chat assistant had nothing to ground a feeling
     * against and would free-associate among six labels.
     *
     * Published verbatim in the chat prompt and in
     * {@see \App\Ai\Tools\SetSiteStyle}'s schema description, which is the whole
     * mechanism: a brand reference ("like Apple") is translated into adjectives
     * and matched here, so no brand name ever reaches storage. Keep the lists
     * disjoint — an adjective that names two presets grounds neither, and
     * `StylePresetTest` asserts it.
     *
     * @return list<string>
     */
    public function synonyms(): array
    {
        return match ($this) {
            self::WarmCraft => ['warm', 'welcoming', 'homely', 'rustic', 'organic', 'inviting'],
            self::ProfessionalMinimal => ['premium', 'refined', 'upmarket', 'sleek', 'minimal', 'understated', 'serious', 'expensive'],
            self::FreshModern => ['modern', 'fresh', 'clean', 'crisp', 'contemporary', 'techy'],
            self::BoldEditorial => ['bold', 'dramatic', 'high-contrast', 'striking', 'confident', 'edgy'],
            self::CalmCoastal => ['calm', 'airy', 'gentle', 'soothing', 'light', 'spacious'],
            self::PlayfulFriendly => ['playful', 'friendly', 'fun', 'approachable', 'colourful', 'casual'],
        };
    }

    /**
     * Industry-vibe tags the AI matches a business against when choosing a
     * preset.
     *
     * @return list<string>
     */
    public function vibes(): array
    {
        return match ($this) {
            self::WarmCraft => ['warm', 'handmade', 'artisan', 'bakery', 'spa', 'craft'],
            self::ProfessionalMinimal => ['professional', 'corporate', 'law', 'finance', 'consulting', 'clean'],
            self::FreshModern => ['fresh', 'startup', 'tech', 'modern', 'saas'],
            self::BoldEditorial => ['bold', 'fashion', 'studio', 'photography', 'editorial'],
            self::CalmCoastal => ['calm', 'wellness', 'clinic', 'dental', 'coastal', 'yoga'],
            self::PlayfulFriendly => ['playful', 'family', 'kids', 'pets', 'fun', 'casual'],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::WarmCraft => 'Warm craft',
            self::ProfessionalMinimal => 'Professional minimal',
            self::FreshModern => 'Fresh modern',
            self::BoldEditorial => 'Bold editorial',
            self::CalmCoastal => 'Calm coastal',
            self::PlayfulFriendly => 'Playful friendly',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::WarmCraft => 'Earthy tones, elegant serifs and generous spacing — for artisan and hospitality businesses.',
            self::ProfessionalMinimal => 'Monochrome, tight and typographic — for services that sell trust.',
            self::FreshModern => 'Green-tinted, geometric and energetic — for modern brands.',
            self::BoldEditorial => 'High-contrast plum, sharp corners and magazine typography.',
            self::CalmCoastal => 'Cool blues, soft corners and airy spacing — for wellness and care.',
            self::PlayfulFriendly => 'Sunset colors, round shapes and a friendly voice.',
        };
    }
}
