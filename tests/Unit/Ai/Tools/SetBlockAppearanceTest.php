<?php

declare(strict_types=1);

use App\Actions\Pages\UpdatePageBlock;
use App\Ai\PageDraft;
use App\Ai\Tools\SetBlockAppearance;
use App\Site\Blocks\BlockVocabulary;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

function appearanceTool(PageDraft $draft): SetBlockAppearance
{
    return new SetBlockAppearance($draft, resolve(BlockVocabulary::class), resolve(UpdatePageBlock::class));
}

function appearanceDraft(): PageDraft
{
    return new PageDraft([
        ['key' => 'k1', 'type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Hello']],
        ['key' => 'k2', 'type' => 'features', 'data' => [
            'variant' => 'grid',
            'appearance' => ['tone' => 'muted', 'spacing' => 'tight'],
            'heading' => 'Why us',
        ]],
        // Chrome never legitimately reaches a page draft; the tool guards anyway.
        ['key' => 'k3', 'type' => 'footer', 'data' => ['variant' => 'minimal']],
    ]);
}

it('sets both dimensions on a block that had no appearance', function (): void {
    $draft = appearanceDraft();

    $result = appearanceTool($draft)->handle(new Request([
        'key' => 'k1',
        'tone' => 'inverted',
        'spacing' => 'tall',
    ]));

    expect($draft->blocks()[0]['data']['appearance'])->toBe(['tone' => 'inverted', 'spacing' => 'tall'])
        // Copy and layout are untouched: this tool only ever writes the one key.
        ->and($draft->blocks()[0]['data']['heading'])->toBe('Hello')
        ->and($draft->blocks()[0]['data']['variant'])->toBe('centered-minimal')
        ->and($result)->toContain('the inverted background and tall vertical spacing')
        ->and($result)->toContain('Current page blocks:');
});

it('leaves a dimension alone when the call does not mention it', function (): void {
    $draft = appearanceDraft();

    appearanceTool($draft)->handle(new Request(['key' => 'k2', 'tone' => 'accent']));

    // "Make it stand out" must not quietly discard the spacing someone chose.
    expect($draft->blocks()[1]['data']['appearance'])->toBe(['tone' => 'accent', 'spacing' => 'tight']);
});

it('hands a dimension back to the layout default, and drops the key when both are cleared', function (): void {
    $draft = appearanceDraft();
    $tool = appearanceTool($draft);

    $tool->handle(new Request(['key' => 'k2', 'tone' => 'layout-default']));

    expect($draft->blocks()[1]['data']['appearance'])->toBe(['spacing' => 'tight']);

    $result = $tool->handle(new Request(['key' => 'k2', 'spacing' => 'layout-default']));

    // No empty array left behind: the editor's commit strips those, and an
    // empty one would count as a change against RecordPageRevision's `===`.
    expect($draft->blocks()[1]['data'])->not->toHaveKey('appearance')
        ->and($result)->toContain("its layout's own background and spacing");
});

/*
 * pages.blocks is `json`, NOT `jsonb`, precisely so key order survives, and
 * RecordPageRevision compares with `===`. Assigning into the existing slot
 * rather than rebuilding `data` is what keeps a restyle from manufacturing a
 * spurious revision on the operator's next Save.
 */
it('preserves the stored key order when the block already has an appearance', function (): void {
    $draft = appearanceDraft();
    $before = array_keys($draft->blocks()[1]['data']);

    appearanceTool($draft)->handle(new Request(['key' => 'k2', 'tone' => 'inverted']));

    expect(array_keys($draft->blocks()[1]['data']))->toBe($before);
});

it('discards a malformed stored appearance instead of carrying it forward', function (): void {
    $draft = new PageDraft([
        ['key' => 'k1', 'type' => 'hero', 'data' => [
            // Hand-edited or imported junk: a key that is not a dimension, and a
            // dimension whose value is not a string.
            'appearance' => ['tone' => ['muted'], 'colour' => 'red'],
            'heading' => 'Hello',
        ]],
    ]);

    appearanceTool($draft)->handle(new Request(['key' => 'k1', 'spacing' => 'airy']));

    expect($draft->blocks()[0]['data']['appearance'])->toBe(['spacing' => 'airy']);
});

it('requires at least one dimension', function (): void {
    $draft = appearanceDraft();

    $result = appearanceTool($draft)->handle(new Request(['key' => 'k1']));

    expect($draft->blocks())->toBe(appearanceDraft()->blocks())
        ->and($result)->toContain('set a background, a vertical spacing, or both');
});

it('rejects a value outside the scale, and names the real ones', function (array $arguments, string $expected, string $listed): void {
    $draft = appearanceDraft();

    $result = appearanceTool($draft)->handle(new Request(['key' => 'k1', ...$arguments]));

    expect($draft->blocks())->toBe(appearanceDraft()->blocks())
        ->and($result)->toContain($expected)
        ->and($result)->toContain($listed);
})->with([
    'unknown tone' => [['tone' => 'neon'], "'neon' is not a background", 'base, muted, accent, inverted, plain'],
    'unknown spacing' => [['spacing' => 'enormous'], "'enormous' is not a spacing", 'flush, tight, normal, airy, tall'],
]);

it('refuses to restyle site chrome', function (): void {
    $draft = appearanceDraft();

    $result = appearanceTool($draft)->handle(new Request(['key' => 'k3', 'tone' => 'accent']));

    expect($draft->blocks())->toBe(appearanceDraft()->blocks())
        ->and($result)->toContain('part of the site frame, not a section of this page');
});

it('refuses a type it does not know', function (): void {
    $draft = new PageDraft([['key' => 'k1', 'type' => 'mystery', 'data' => []]]);

    $result = appearanceTool($draft)->handle(new Request(['key' => 'k1', 'tone' => 'muted']));

    expect($draft->blocks()[0]['data'])->toBeEmpty()
        ->and($result)->toContain('not a section of this page');
});

it('rejects a key that is not on the page', function (mixed $key): void {
    $draft = appearanceDraft();

    $result = appearanceTool($draft)->handle(new Request(['key' => $key, 'tone' => 'muted']));

    expect($draft->blocks())->toBe(appearanceDraft()->blocks())
        ->and($result)->toContain('no block with that key');
})->with([
    'unknown' => ['ghost'],
    'missing' => [null],
]);

it('publishes both scales plus the reset sentinel, with guidance on when to use each', function (): void {
    $tool = appearanceTool(appearanceDraft());

    $serialized = json_decode(json_encode(array_map(
        fn ($type): array => $type->toArray(),
        $tool->schema(new JsonSchemaTypeFactory),
    )), associative: true);

    expect($serialized['tone']['enum'])
        ->toBe(['base', 'muted', 'accent', 'inverted', 'plain', 'layout-default'])
        ->and($serialized['spacing']['enum'])
        ->toBe(['flush', 'tight', 'normal', 'airy', 'tall', 'layout-default'])
        ->and($serialized['key']['type'])->toBe('string')
        // Descriptions carry the enums' own guidance: five colour names alone
        // leave the model choosing by vibe, and over-use is the real failure.
        ->and($serialized['tone']['description'])->toContain('dramatic')
        ->and($serialized['spacing']['description'])->toContain('the default, and right for most sections')
        ->and($tool->description())->toContain('rhythm');
});
