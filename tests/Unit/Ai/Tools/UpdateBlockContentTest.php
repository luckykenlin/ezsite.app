<?php

declare(strict_types=1);

use App\Actions\Pages\UpdatePageBlock;
use App\Ai\BlockDataSanitizer;
use App\Ai\PageDraft;
use App\Ai\Tools\UpdateBlockContent;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Tools\Request;

function updateTool(PageDraft $draft): UpdateBlockContent
{
    return new UpdateBlockContent(
        $draft,
        resolve(BlockDataSanitizer::class),
        resolve(UpdatePageBlock::class),
    );
}

function heroDraft(array $data = ['variant' => 'centered-minimal', 'heading' => 'Old headline']): PageDraft
{
    return new PageDraft([['key' => 'k1', 'type' => 'hero', 'data' => $data]]);
}

it('merges the given fields into the block and leaves the rest alone', function (): void {
    $draft = heroDraft(['variant' => 'centered-minimal', 'heading' => 'Old', 'subheading' => 'Keep me']);

    $result = updateTool($draft)->handle(new Request([
        'key' => 'k1',
        'content' => ['heading' => 'New headline'],
    ]));

    expect($draft->blocks()[0]['data'])->toBe([
        'variant' => 'centered-minimal',
        'heading' => 'New headline',
        'subheading' => 'Keep me',
    ])->and($result)->toContain('Updated the hero block (heading)');
});

it('never lets the model change the layout variant or bind target', function (): void {
    Log::spy();

    $draft = heroDraft(['variant' => 'centered-minimal', 'heading' => 'Old']);

    updateTool($draft)->handle(new Request([
        'key' => 'k1',
        'content' => ['heading' => 'New', 'variant' => 'full-bleed-overlay'],
    ]));

    // Layout is the operator's choice through the Design/variant controls.
    expect($draft->blocks()[0]['data']['variant'])->toBe('centered-minimal')
        ->and($draft->blocks()[0]['data']['heading'])->toBe('New');
});

it('de-tags the copy it writes', function (): void {
    $draft = heroDraft();

    updateTool($draft)->handle(new Request([
        'key' => 'k1',
        'content' => ['heading' => '<script>alert(1)</script>Hi'],
    ]));

    expect($draft->blocks()[0]['data']['heading'])->toBe('alert(1)Hi');
});

it('rejects an invented field name and hands the page back so the model can retry', function (): void {
    Log::spy();

    $draft = heroDraft();

    $result = updateTool($draft)->handle(new Request([
        'key' => 'k1',
        'content' => ['headline' => 'Wrong field name'],
    ]));

    expect($draft->blocks())->toBe(heroDraft()->blocks())
        ->and($result)->toContain("None of those field names exist on a 'hero' block")
        ->and($result)->toContain('Current page blocks:');
});

it('answers an unknown key with the current page instead of failing the turn', function (mixed $key): void {
    $draft = heroDraft();

    $result = updateTool($draft)->handle(new Request(['key' => $key, 'content' => ['heading' => 'Hi']]));

    expect($draft->blocks())->toBe(heroDraft()->blocks())
        ->and($result)->toContain('There is no block with that key')
        ->and($result)->toContain('Current page blocks:');
})->with([
    'hallucinated key' => ['no-such-key'],
    'missing key' => [null],
    'wrong type' => [42],
]);

it('treats empty or non-object content as a no-op', function (mixed $content): void {
    $draft = heroDraft();

    $result = updateTool($draft)->handle(new Request(['key' => 'k1', 'content' => $content]));

    expect($draft->blocks())->toBe(heroDraft()->blocks())
        ->and($result)->toContain('No content fields were given');
})->with([
    'empty object' => [[]],
    'a string' => ['heading: hi'],
    'missing' => [null],
]);

it('requires a key and a content object', function (): void {
    $schema = updateTool(heroDraft())->schema(new JsonSchemaTypeFactory);

    $serialized = json_decode(json_encode(array_map(
        fn ($type): array => $type->toArray(),
        $schema,
    )), associative: true);

    expect($serialized['key']['type'])->toBe('string')
        ->and($serialized['content']['type'])->toBe('object')
        // Merge semantics belong in the description: a model that thinks this
        // replaces the block sends every field and blanks what it omits.
        ->and(updateTool(heroDraft())->description())->toContain('Send only the fields you want to change');
});
