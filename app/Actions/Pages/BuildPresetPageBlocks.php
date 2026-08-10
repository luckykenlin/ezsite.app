<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Ai\SiteDraftValidator;
use App\Design\StylePreset;
use App\Site\BindResolver;
use App\Templates\PagePreset;
use App\Templates\PlaceholderSubstitution;

/**
 * One page preset, made render-ready for THIS tenant: placeholder copy filled
 * from the business profile, sanitized through the same validating door as AI
 * draft output, and re-laid to the site's own style preset.
 *
 * The whole pipeline lives here rather than split between the picker and its
 * thumbnails so the preview iframe and the created page are byte-for-byte the
 * same blocks — a thumbnail that renders through a different path is a
 * thumbnail that lies. The ordering copies the template pipeline exactly:
 * fill BEFORE validation (a filled value goes through the same sanitization
 * as authored text), then {@see StampPresetDefaults::fill()} — back-fill, not
 * overwrite, because a preset's authored variants are designed choices that
 * must survive, the same semantics {@see \App\Actions\ApplySiteDraft} uses.
 *
 * Unlike {@see \App\Actions\Templates\FillTemplatePlaceholders}, unanswered
 * tokens fall back to NEUTRAL copy rather than a demo profile: this runs on a
 * real tenant, and inventing a plausible-looking city or phone number would
 * be worse than an obvious replace-me.
 */
final readonly class BuildPresetPageBlocks
{
    public function __construct(
        private BindResolver $binds,
        private SiteDraftValidator $validator,
        private StampPresetDefaults $stamp,
    ) {
        //
    }

    /**
     * @return array{title: string, metaDescription: string|null, blocks: list<array{type: string, data: array<string, mixed>}>}
     */
    public function handle(PagePreset $preset): array
    {
        $definition = $preset->definition();
        $values = $this->values();

        $blocks = $this->validator->blocks(
            PlaceholderSubstitution::fill($definition->blocks, $values),
        );

        $stylePreset = $this->binds->business()?->design_tokens->preset;

        if ($stylePreset instanceof StylePreset) {
            $blocks = $this->stamp->fill($blocks, $stylePreset);
        }

        $meta = $definition->metaDescription;

        return [
            'title' => $definition->title,
            'metaDescription' => $meta === null ? null : strtr($meta, $values),
            'blocks' => $blocks,
        ];
    }

    /**
     * The substitution map from the tenant's own records — primary location
     * first for the place-and-reach facts, business fields as the fallback,
     * neutral replace-me copy when neither row answers.
     *
     * @return array<string, string>
     */
    private function values(): array
    {
        $business = $this->binds->business();
        $location = $this->binds->location(null);

        return [
            '{business_name}' => $this->firstFilled([$business?->name], 'Your business'),
            '{tagline}' => $this->firstFilled([$business?->tagline], 'A line that says what you do'),
            '{city}' => $this->firstFilled([$location?->city], 'your area'),
            '{phone}' => $this->firstFilled([$location?->phone, $business?->contact_phone], '(000) 000-0000'),
            '{email}' => $this->firstFilled([$location?->email, $business?->contact_email], 'hello@example.com'),
        ];
    }

    /**
     * @param  list<string|null>  $candidates
     */
    private function firstFilled(array $candidates, string $fallback): string
    {
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && mb_trim($candidate) !== '') {
                return mb_trim($candidate);
            }
        }

        return $fallback;
    }
}
