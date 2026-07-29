<?php

declare(strict_types=1);

use App\Actions\Pages\ReorderPageBlocks;
use App\Ai\PageDraft;
use App\Ai\Tools\ReorderBlocks;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

function reorderTool(PageDraft $draft): ReorderBlocks
{
    return new ReorderBlocks($draft, resolve(ReorderPageBlocks::class));
}

function orderableDraft(): PageDraft
{
    return new PageDraft([
        ['key' => 'k1', 'type' => 'hero', 'data' => []],
        ['key' => 'k2', 'type' => 'features', 'data' => []],
        ['key' => 'k3', 'type' => 'testimonials', 'data' => []],
    ]);
}

it('applies a complete new order', function (): void {
    $draft = orderableDraft();

    $result = reorderTool($draft)->handle(new Request(['keys' => ['k1', 'k3', 'k2']]));

    expect(array_column($draft->blocks(), 'type'))->toBe(['hero', 'testimonials', 'features'])
        ->and($result)->toContain('Reordered the page');
});

/*
 * ReorderPageBlocks throws on any mismatch — a guard against a stale drag
 * payload silently dropping a block. A partial or hallucinated key list is a
 * routine model mistake, so the tool must catch it BEFORE the action and answer
 * with a correction; letting the exception through would fail the whole turn.
 */
it('rejects an incomplete order and names what is missing', function (): void {
    $draft = orderableDraft();

    $result = reorderTool($draft)->handle(new Request(['keys' => ['k3', 'k1']]));

    expect($draft->blocks())->toBe(orderableDraft()->blocks())
        ->and($result)->toContain('Missing: k2.')
        ->and($result)->toContain('Current page blocks:');
});

it('rejects keys that are not on the page and names them', function (): void {
    $draft = orderableDraft();

    $result = reorderTool($draft)->handle(new Request(['keys' => ['k1', 'k2', 'ghost']]));

    expect($draft->blocks())->toBe(orderableDraft()->blocks())
        ->and($result)->toContain('Not on this page: ghost.');
});

it('rejects a duplicated key even though the count looks right', function (): void {
    $draft = orderableDraft();

    $result = reorderTool($draft)->handle(new Request(['keys' => ['k1', 'k2', 'k2']]));

    expect($draft->blocks())->toBe(orderableDraft()->blocks())
        ->and($result)->toContain('Missing: k3.');
});

it('rejects an absent or unusable key list', function (mixed $keys): void {
    $draft = orderableDraft();

    $result = reorderTool($draft)->handle(new Request(['keys' => $keys]));

    expect($draft->blocks())->toBe(orderableDraft()->blocks())
        ->and($result)->toContain('must list every key on the page exactly once');
})->with([
    'missing' => [null],
    'empty' => [[]],
    'not an array' => ['k1,k2,k3'],
    'non-string entries' => [[1, 2, 3]],
]);

it('takes a list of keys and describes itself', function (): void {
    $serialized = json_decode(json_encode(array_map(
        fn ($type): array => $type->toArray(),
        reorderTool(orderableDraft())->schema(new JsonSchemaTypeFactory),
    )), associative: true);

    expect($serialized['keys']['type'])->toBe('array')
        ->and($serialized['keys']['items']['type'])->toBe('string')
        ->and(reorderTool(orderableDraft())->description())->not->toBeEmpty();
});
