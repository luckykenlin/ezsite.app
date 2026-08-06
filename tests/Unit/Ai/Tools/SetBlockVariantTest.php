<?php

declare(strict_types=1);

use App\Actions\Pages\UpdatePageBlock;
use App\Ai\PageDraft;
use App\Ai\Tools\SetBlockVariant;
use App\Site\Blocks\BlockVocabulary;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

function variantTool(PageDraft $draft): SetBlockVariant
{
    return new SetBlockVariant($draft, resolve(BlockVocabulary::class), resolve(UpdatePageBlock::class));
}

function variantDraft(): PageDraft
{
    return new PageDraft([
        ['key' => 'k1', 'type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Hello']],
        ['key' => 'k2', 'type' => 'heading', 'data' => ['content' => 'A section']],
    ]);
}

it('switches a block to another layout its type offers', function (): void {
    $draft = variantDraft();

    $result = variantTool($draft)->handle(new Request(['key' => 'k1', 'variant' => 'full-bleed-overlay']));

    expect($draft->blocks()[0]['data']['variant'])->toBe('full-bleed-overlay')
        // Content is untouched: variants are per type, and every view of a type
        // reads the same props.
        ->and($draft->blocks()[0]['data']['heading'])->toBe('Hello')
        ->and($result)->toContain('Set the hero block layout to full-bleed-overlay');
});

/*
 * pages.blocks is `json`, NOT `jsonb`, precisely so key order survives, and
 * RecordPageRevision compares with `===`. Rebuilding `data` instead of assigning
 * into the existing slot would reorder the keys and manufacture a spurious
 * revision on the operator's next Save — invisible until their history is full
 * of edits they did not make.
 */
it('preserves the stored key order of the block data', function (): void {
    $draft = variantDraft();
    $before = array_keys($draft->blocks()[0]['data']);

    variantTool($draft)->handle(new Request(['key' => 'k1', 'variant' => 'left-text-right-image']));

    expect(array_keys($draft->blocks()[0]['data']))->toBe($before);
});

it('rejects a layout the block type does not offer, and lists the real ones', function (): void {
    $draft = variantDraft();

    $result = variantTool($draft)->handle(new Request(['key' => 'k1', 'variant' => 'masonry']));

    expect($draft->blocks())->toBe(variantDraft()->blocks())
        ->and($result)->toContain('A hero block has no such layout')
        ->and($result)->toContain('centered-minimal')
        ->and($result)->toContain('Current page blocks:');
});

/*
 * `heading` declares no variants at all, so resolveVariant() answers null for
 * everything — the tool must say so rather than offering an empty list.
 */
it('says so when the block type has a single fixed layout', function (): void {
    $draft = variantDraft();

    $result = variantTool($draft)->handle(new Request(['key' => 'k2', 'variant' => 'grid']));

    expect($draft->blocks())->toBe(variantDraft()->blocks())
        ->and($result)->toContain('single fixed layout');
});

it('rejects a key that is not on the page', function (mixed $key): void {
    $draft = variantDraft();

    $result = variantTool($draft)->handle(new Request(['key' => $key, 'variant' => 'centered-minimal']));

    expect($draft->blocks())->toBe(variantDraft()->blocks())
        ->and($result)->toContain('no block with that key');
})->with([
    'unknown' => ['ghost'],
    'missing' => [null],
]);

it('offers every known layout in its schema and says what it does not touch', function (): void {
    $serialized = json_decode(json_encode(array_map(
        fn ($type): array => $type->toArray(),
        variantTool(variantDraft())->schema(new JsonSchemaTypeFactory),
    )), associative: true);

    expect($serialized['variant']['enum'])
        // The union across types — a JSON Schema cannot make one field's options
        // depend on another's, so handle() re-checks against this block's type.
        ->toContain('centered-minimal', 'grid', 'masonry')
        ->and($serialized['key']['type'])->toBe('string')
        // The load-bearing clause: the model must know this leaves copy alone,
        // or it pairs every layout switch with a needless rewrite.
        ->and(variantTool(variantDraft())->description())->toContain('without touching its words');
});
