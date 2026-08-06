<?php

declare(strict_types=1);

use App\Actions\Pages\AddPageBlock;
use App\Ai\PageDraft;
use App\Ai\Tools\AddBlock;
use App\Site\Blocks\BlockVocabulary;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

function addTool(PageDraft $draft): AddBlock
{
    return new AddBlock($draft, resolve(AddPageBlock::class), resolve(BlockVocabulary::class));
}

function twoBlockDraft(): PageDraft
{
    return new PageDraft([
        ['key' => 'k1', 'type' => 'hero', 'data' => ['heading' => 'Welcome']],
        ['key' => 'k2', 'type' => 'cta', 'data' => ['heading' => 'Come by']],
    ]);
}

it('appends a block with sample content and reports its new key', function (): void {
    $draft = twoBlockDraft();

    $result = addTool($draft)->handle(new Request(['type' => 'features']));

    $added = $draft->blocks()[2];

    expect(array_column($draft->blocks(), 'type'))->toBe(['hero', 'cta', 'features'])
        // Sample content means a half-finished turn still leaves a renderable page.
        ->and($added['data'])->not->toBeEmpty()
        ->and($result)->toContain('Added a features block with key '.$added['key']);
});

it('inserts at an explicit position', function (): void {
    $draft = twoBlockDraft();

    addTool($draft)->handle(new Request(['type' => 'gallery', 'position' => 0]));

    expect(array_column($draft->blocks(), 'type'))->toBe(['gallery', 'hero', 'cta']);
});

it('appends when the position is not a usable integer', function (mixed $position): void {
    $draft = twoBlockDraft();

    addTool($draft)->handle(new Request(['type' => 'gallery', 'position' => $position]));

    expect(array_column($draft->blocks(), 'type'))->toBe(['hero', 'cta', 'gallery']);
})->with([
    'missing' => [null],
    'a string' => ['first'],
]);

it('refuses site chrome and unknown types, listing what it will accept', function (string $type): void {
    $draft = twoBlockDraft();

    $result = addTool($draft)->handle(new Request(['type' => $type]));

    expect($draft->blocks())->toBe(twoBlockDraft()->blocks())
        ->and($result)->toContain("'".$type."' is not a block type you can add")
        ->and($result)->toContain('hero');
})->with([
    // Header and footer are site-wide chrome, not page content.
    'header' => ['header'],
    'footer' => ['footer'],
    'invented' => ['pricing-table'],
]);

it('refuses a non-string type', function (): void {
    $draft = twoBlockDraft();

    expect(addTool($draft)->handle(new Request(['type' => 42])))
        ->toContain("'that' is not a block type you can add");
});

it('enumerates the addable types in its schema, excluding chrome', function (): void {
    $serialized = json_decode(json_encode(array_map(
        fn ($type): array => $type->toArray(),
        addTool(twoBlockDraft())->schema(new JsonSchemaTypeFactory),
    )), associative: true);

    expect($serialized['type']['enum'])->toContain('hero', 'features', 'cta')
        ->and($serialized['type']['enum'])->not->toContain('header')
        ->and($serialized['type']['enum'])->not->toContain('footer')
        ->and($serialized['position']['type'])->toBe('integer')
        // A block arrives with placeholder copy, so the description has to send
        // the model on to UpdateBlockContent — otherwise it stops at sample text.
        ->and(addTool(twoBlockDraft())->description())->toContain('UpdateBlockContent');
});
