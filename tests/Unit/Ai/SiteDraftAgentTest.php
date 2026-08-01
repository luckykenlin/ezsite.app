<?php

declare(strict_types=1);

use App\Ai\Agents\SiteDraftAgent;
use App\Ai\SiteDraftValidator;
use App\Design\StylePreset;
use App\Site\Blocks\BlockVocabulary;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;

it('constrains the structured output to presets and non-chrome vocabulary types', function (): void {
    $schema = new SiteDraftAgent(resolve(BlockVocabulary::class))->schema(new JsonSchemaTypeFactory);

    $serialized = json_decode(json_encode(array_map(
        fn ($type): array => $type->toArray(),
        $schema,
    )), associative: true);

    expect($serialized['preset']['enum'])->toContain('warm-craft', 'professional-minimal')
        ->and($serialized['preset']['enum'])->toHaveSameSize(StylePreset::cases())
        // The home page plus the fixed extra-slug menu — a closed set, so
        // slugs are collision-free and the navigation can be stamped.
        ->and($serialized['pages']['maxItems'])->toBe(1 + count(SiteDraftValidator::EXTRA_SLUGS))
        ->and($serialized['pages']['items']['properties']['slug']['enum'])
        ->toBe(['/', ...SiteDraftValidator::EXTRA_SLUGS]);

    $blockType = $serialized['pages']['items']['properties']['blocks']['items']['properties']['type'];

    expect($blockType['enum'])->toContain('hero', 'features', 'testimonials', 'gallery', 'cta', 'contact', 'heading')
        ->and($blockType['enum'])->not->toContain('header')
        ->and($blockType['enum'])->not->toContain('footer');

    // The copy is free to evolve, but two clauses are load-bearing: the block
    // views escape everything (so markup would render as text), and the draft is
    // persisted verbatim (so an invented fact reaches the live site).
    expect(new SiteDraftAgent(resolve(BlockVocabulary::class))->instructions())
        ->toContain('never output HTML')
        ->toContain('never invent facts');
});
