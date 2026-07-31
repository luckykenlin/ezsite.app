<?php

declare(strict_types=1);

namespace App\Design;

/**
 * The tenant's chosen design tokens — enum keys only, never derived CSS
 * values (those live in the enums so they can evolve without data
 * migrations). Reads are lenient: any unknown/missing stored value falls
 * back to the default silently, so bad data can never break a page render.
 * Writes are strict — see App\Actions\UpdateDesignTokens.
 */
final readonly class DesignTokens
{
    public function __construct(
        public ?StylePreset $preset,
        public ColorPalette $palette,
        public FontPair $fontPair,
        public RadiusScale $radius,
        public SpacingDensity $density,
    ) {
        //
    }

    public static function default(): self
    {
        return new self(
            preset: null,
            palette: ColorPalette::Default,
            fontPair: FontPair::ModernSans,
            radius: RadiusScale::Md,
            density: SpacingDensity::Normal,
        );
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            preset: is_string($data['preset'] ?? null) ? StylePreset::tryFrom($data['preset']) : null,
            palette: (is_string($data['palette'] ?? null) ? ColorPalette::tryFrom($data['palette']) : null) ?? ColorPalette::Default,
            fontPair: (is_string($data['font_pair'] ?? null) ? FontPair::tryFrom($data['font_pair']) : null) ?? FontPair::ModernSans,
            radius: (is_string($data['radius'] ?? null) ? RadiusScale::tryFrom($data['radius']) : null) ?? RadiusScale::Md,
            density: (is_string($data['density'] ?? null) ? SpacingDensity::tryFrom($data['density']) : null) ?? SpacingDensity::Normal,
        );
    }

    /**
     * @return array{preset: string|null, palette: string, font_pair: string, radius: string, density: string}
     */
    public function toArray(): array
    {
        return [
            'preset' => $this->preset?->value,
            'palette' => $this->palette->value,
            'font_pair' => $this->fontPair->value,
            'radius' => $this->radius->value,
            'density' => $this->density->value,
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
        $values = array_map(
            fn (TokenKey $key): string => $key->valueOn($this),
            TokenKey::cases(),
        );

        $preset = $this->preset;

        return ($preset instanceof StylePreset ? $preset->value : 'custom').' — '.implode(', ', $values);
    }

    /**
     * A copy with the given tokens replaced. Any manual override detaches
     * the preset marker — the combination is custom from then on.
     */
    public function with(
        ?ColorPalette $palette = null,
        ?FontPair $fontPair = null,
        ?RadiusScale $radius = null,
        ?SpacingDensity $density = null,
    ): self {
        return new self(
            preset: null,
            palette: $palette ?? $this->palette,
            fontPair: $fontPair ?? $this->fontPair,
            radius: $radius ?? $this->radius,
            density: $density ?? $this->density,
        );
    }
}
