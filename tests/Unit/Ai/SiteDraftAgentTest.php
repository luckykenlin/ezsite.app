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

    expect($serialized['preset']['enum'])->toBe(array_column(StylePreset::cases(), 'value'))
        ->and($serialized['pages']['maxItems'])->toBe(1);

    $blockType = $serialized['pages']['items']['properties']['blocks']['items']['properties']['type'];

    expect($blockType['enum'])->toContain('hero', 'features', 'testimonials', 'gallery', 'cta', 'contact', 'heading')
        ->and($blockType['enum'])->not->toContain('header')
        ->and($blockType['enum'])->not->toContain('footer');
});

it('instructs the agent to stay inside the vocabulary and requested language', function (): void {
    $instructions = new SiteDraftAgent()->instructions();

    expect($instructions)->toContain('never output HTML')
        ->toContain('never invent facts')
        ->toContain('requested language');
});
