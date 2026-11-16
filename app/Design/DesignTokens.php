<?php

declare(strict_types=1);

namespace App\Design;

/**
 * The tenant's chosen design tokens — enum keys only, never derived CSS
 * values (those live in the enums so they can evolve without data
 * migrations). Reads are lenient: any unknown/missing stored value falls
 * back to the default silently, so bad data can never break a page render.
 *
 * The eight axes are the INTERNAL representation of a look, not a form: a
 * site's look is a theme {@see \App\Themes\Scheme} plus the business's brand
 * hexes, and only a scheme or a template definition constructs one of these.
 * There is deliberately no `with()` — nothing may change one axis independently
 * of the scheme it came from, which is what kept the old free-axis editors
 * producing combinations nobody designed.
 *
 * `$scheme` is the slug of the theme scheme these tokens were taken from, or
 * null for a look no scheme vouches for. A bare string rather than a
 * {@see \App\Themes\Scheme}, so this module stays unaware of themes: the
 * tokens are stored data, and whether the slug still names a scheme in the
 * tenant's current theme is the reader's question (`ThemeDefinition::scheme()`
 * answers null), not something to validate on every hydration.
 */
final readonly class DesignTokens
{
    public function __construct(
        public ?string $scheme,
        public ColorPalette $palette,
        public FontPair $fontPair,
        public RadiusScale $radius,
        public SpacingDensity $density,
        public TypeStyle $typeStyle = TypeStyle::Classic,
        public SectionDivider $divider = SectionDivider::None,
        public AccentStyle $accent = AccentStyle::Flat,
        public MotionStyle $motion = MotionStyle::Still,
    ) {
        //
    }

    public static function default(): self
    {
        return new self(
            scheme: null,
            palette: ColorPalette::Default,
            fontPair: FontPair::NeoGrotesque,
            radius: RadiusScale::Md,
            density: SpacingDensity::Normal,
            typeStyle: TypeStyle::Classic,
            divider: SectionDivider::None,
            accent: AccentStyle::Flat,
            motion: MotionStyle::Still,
        );
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            scheme: is_string($data['scheme'] ?? null) ? $data['scheme'] : null,
            palette: (is_string($data['palette'] ?? null) ? ColorPalette::tryFrom($data['palette']) : null) ?? ColorPalette::Default,
            fontPair: (is_string($data['font_pair'] ?? null) ? FontPair::tryFrom($data['font_pair']) : null) ?? FontPair::NeoGrotesque,
            radius: (is_string($data['radius'] ?? null) ? RadiusScale::tryFrom($data['radius']) : null) ?? RadiusScale::Md,
            density: (is_string($data['density'] ?? null) ? SpacingDensity::tryFrom($data['density']) : null) ?? SpacingDensity::Normal,
            typeStyle: (is_string($data['type_style'] ?? null) ? TypeStyle::tryFrom($data['type_style']) : null) ?? TypeStyle::Classic,
            divider: (is_string($data['divider'] ?? null) ? SectionDivider::tryFrom($data['divider']) : null) ?? SectionDivider::None,
            accent: (is_string($data['accent'] ?? null) ? AccentStyle::tryFrom($data['accent']) : null) ?? AccentStyle::Flat,
            motion: (is_string($data['motion'] ?? null) ? MotionStyle::tryFrom($data['motion']) : null) ?? MotionStyle::Still,
        );
    }

    /**
     * @return array{scheme: string|null, palette: string, font_pair: string, type_style: string, radius: string, density: string, divider: string, accent: string, motion: string}
     */
    public function toArray(): array
    {
        return [
            'scheme' => $this->scheme,
            'palette' => $this->palette->value,
            'font_pair' => $this->fontPair->value,
            'type_style' => $this->typeStyle->value,
            'radius' => $this->radius->value,
            'density' => $this->density->value,
            'divider' => $this->divider->value,
            'accent' => $this->accent->value,
            'motion' => $this->motion->value,
        ];
    }

    /**
     * This look in one line, e.g. `warm-craft — warm-sand, elegant-serif, lg,
     * spacious`.
     *
     * For the chat assistant, which needs to know where the site currently
     * stands before it can act on "make it warmer" — warmer than what is not
     * answerable from the request alone. Lives here rather than on either
     * consumer ({@see \App\Ai\Prompts\PageEditPrompt} describes the tokens the
     * turn started from, {@see \App\Ai\Tools\SetSiteStyle} the ones it staged)
     * so the two can never drift into describing the same tokens differently.
     */
    public function describe(): string
    {
        $axes = $this->toArray();
        unset($axes['scheme']);

        return ($this->scheme ?? 'custom').' — '.implode(', ', $axes);
    }
}
