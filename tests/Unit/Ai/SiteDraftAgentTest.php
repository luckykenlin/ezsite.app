<?php

declare(strict_types=1);

use App\Ai\Agents\SiteDraftAgent;
use App\Design\StylePreset;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;

it('constrains the structured output to presets and non-chrome vocabulary types', function (): void {
    $schema = new SiteDraftAgent()->schema(new JsonSchemaTypeFactory);

    $serialized = json_decode(json_encode(array_map(
        fn ($type): array => $type->toArray(),
        $schema,
    )), associative: true);

    expect($serialized['preset']['enum'])->toContain('warm-craft', 'professional-minimal')
        ->and($serialized['preset']['enum'])->toHaveSameSize(StylePreset::cases())
        ->and($serialized['pages']['maxItems'])->toBe(1);

    $blockType = $serialized['pages']['items']['properties']['blocks']['items']['properties']['type'];

    expect($blockType['enum'])->toContain('hero', 'features', 'testimonials', 'gallery', 'cta', 'contact', 'heading')
        ->and($blockType['enum'])->not->toContain('header')
        ->and($blockType['enum'])->not->toContain('footer');

    // The copy is free to evolve, but two clauses are load-bearing: the block
    // views escape everything (so markup would render as text), and the draft is
    // persisted verbatim (so an invented fact reaches the live site).
    expect(new SiteDraftAgent()->instructions())
        ->toContain('never output HTML')
        ->toContain('never invent facts');
});
