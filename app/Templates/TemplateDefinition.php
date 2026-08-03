<?php

declare(strict_types=1);

namespace App\Templates;

use App\Design\AccentStyle;
use App\Design\ColorPalette;
use App\Design\DesignTokens;
use App\Design\FontPair;
use App\Design\MotionStyle;
use App\Design\RadiusScale;
use App\Design\SectionDivider;
use App\Design\SpacingDensity;
use App\Design\StylePreset;
use App\Design\TypeStyle;
use Spatie\OpeningHours\OpeningHours;

/**
 * Everything one hand-curated industry template is: a look, a business
 * profile, a set of pages, and the questions to ask before applying it.
 *
 * Templates are PHP, not rows and not AI output, and that is the product
 * decision this class encodes. The closed design system is the moat; a
 * template is that system pointed at one trade, so it is a designed artifact —
 * versioned, diffable, and testable against the same
 * {@see \App\Ai\SiteDraftValidator} the AI path goes through.
 *
 * {@see $pages} is therefore in the validator's INPUT shape, not its output:
 * flat `variant`/`tone`/`spacing` siblings of `type`/`data`, an unprefixed
 * `image_query` inside `data`, and the same closed slug menu. A template that
 * drifts from what the model is allowed to produce is a template that renders
 * through a path nothing else uses — `SiteTemplateTest` runs every one of them through
 * the validator and fails on a single dropped block.
 */
final readonly class TemplateDefinition
{
    /**
     * @param  string  $category  the Business `category`, which also seeds the
     *                            per-item photo searches
     * @param  list<array{type: string, data: array<string, mixed>}>  $chrome  header/footer block entries
     * @param  non-empty-list<array<string, mixed>>  $pages  SiteDraftValidator INPUT shape
     * @param  list<PhotoQuery>  $photoQueries
     * @param  list<TemplateField>  $extraFields
     */
    public function __construct(
        public StylePreset $preset,
        public string $brandPrimary,
        public string $brandSecondary,
        public string $brandAccent,
        public string $category,
        public array $chrome,
        public array $pages,
        public array $photoQueries,
        public DemoProfile $demoProfile,
        public array $extraFields = [],
        public ?ColorPalette $palette = null,
        public ?FontPair $fontPair = null,
        public ?RadiusScale $radius = null,
        public ?SpacingDensity $density = null,
        public ?TypeStyle $typeStyle = null,
        public ?SectionDivider $divider = null,
        public ?AccentStyle $accent = null,
        public ?MotionStyle $motion = null,
    ) {
        //
    }

    /**
     * The preset's tokens with this template's overrides folded in — the value
     * that goes onto `businesses.design_tokens`.
     *
     * The preset MARKER survives an override, unlike {@see DesignTokens::with()},
     * which detaches it. That asymmetry is deliberate on both sides: an
     * operator dragging a token in the design panel really has left the preset,
     * but a template is a curated *variation* of one, and the marker is what
     * {@see \App\Actions\Pages\StampPresetDefaults} and the design panel read to
     * know which preset's per-block opinions still apply. Two WarmCraft
     * templates with different palettes are the "one system, two different
     * restaurants" pitch; dropping them both to `custom` would lose it.
     */
    public function tokens(): DesignTokens
    {
        $base = $this->preset->tokens();

        return new DesignTokens(
            preset: $this->preset,
            palette: $this->palette ?? $base->palette,
            fontPair: $this->fontPair ?? $base->fontPair,
            radius: $this->radius ?? $base->radius,
            density: $this->density ?? $base->density,
            typeStyle: $this->typeStyle ?? $base->typeStyle,
            divider: $this->divider ?? $base->divider,
            accent: $this->accent ?? $base->accent,
            motion: $this->motion ?? $base->motion,
        );
    }

    /**
     * The Business attributes this template's demo site is built from — also
     * the shape {@see \App\Actions\Templates\ProvisionSiteFromTemplate}
     * overwrites with the applying user's own answers.
     *
     * @return array<string, mixed>
     */
    public function businessAttributes(): array
    {
        return [
            'name' => $this->demoProfile->name,
            'category' => $this->category,
            'tagline' => $this->demoProfile->tagline,
            'description' => $this->demoProfile->description,
            'brand_primary' => $this->brandPrimary,
            'brand_secondary' => $this->brandSecondary,
            'brand_accent' => $this->brandAccent,
            'contact_email' => $this->demoProfile->email,
            'contact_phone' => $this->demoProfile->phone,
            'timezone' => $this->demoProfile->timezone,
            'design_tokens' => $this->tokens(),
        ];
    }

    /**
     * The single Location the bound blocks resolve against.
     *
     * @return array<string, mixed>
     */
    public function locationAttributes(): array
    {
        return [
            'label' => $this->demoProfile->city,
            'is_primary' => true,
            'address_line1' => $this->demoProfile->addressLine1,
            'city' => $this->demoProfile->city,
            'state' => $this->demoProfile->state,
            'postal_code' => $this->demoProfile->postalCode,
            'country' => $this->demoProfile->country,
            'phone' => $this->demoProfile->phone,
            'email' => $this->demoProfile->email,
            'timezone' => $this->demoProfile->timezone,
            'opening_hours' => OpeningHours::create($this->demoProfile->openingHours),
        ];
    }
}
