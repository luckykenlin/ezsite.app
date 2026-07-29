<?php

declare(strict_types=1);

use App\Actions\Pages\RemovePageBlock;
use App\Ai\PageDraft;
use App\Ai\Tools\RemoveBlock;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

function removeTool(PageDraft $draft): RemoveBlock
{
    return new RemoveBlock($draft, resolve(RemovePageBlock::class));
}

function removableDraft(): PageDraft
{
    return new PageDraft([
        ['key' => 'k1', 'type' => 'hero', 'data' => ['heading' => 'Welcome']],
        ['key' => 'k2', 'type' => 'gallery', 'data' => []],
    ]);
}

it('removes the addressed block and reports what went', function (): void {
    $draft = removableDraft();

    $result = removeTool($draft)->handle(new Request(['key' => 'k2']));

    expect(array_column($draft->blocks(), 'type'))->toBe(['hero'])
        ->and($result)->toContain('Removed the gallery block');
});

it('answers an unknown key with the current page instead of failing the turn', function (mixed $key): void {
    $draft = removableDraft();

    $result = removeTool($draft)->handle(new Request(['key' => $key]));

    expect($draft->blocks())->toBe(removableDraft()->blocks())
        ->and($result)->toContain('There is no block with that key')
        ->and($result)->toContain('Current page blocks:');
})->with([
    'hallucinated key' => ['no-such-key'],
    'missing key' => [null],
]);

it('takes a key and describes itself', function (): void {
    $serialized = json_decode(json_encode(array_map(
        fn ($type): array => $type->toArray(),
        removeTool(removableDraft())->schema(new JsonSchemaTypeFactory),
    )), associative: true);

    expect($serialized['key']['type'])->toBe('string')
        // The description is what keeps the model from tidying up unprompted.
        ->and(removeTool(removableDraft())->description())->toContain('clearly asked');
});
