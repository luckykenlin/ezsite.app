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
        ->and($result)->toContain('the inverted background, tall vertical spacing')
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
        ->and($result)->toContain("its layout's own defaults");
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
        ->and($result)->toContain('send at least one layout axis');
});

it('rejects a value outside the scale, and names the real ones', function (array $arguments, string $expected, string $listed): void {
    $draft = appearanceDraft();

    $result = appearanceTool($draft)->handle(new Request(['key' => 'k1', ...$arguments]));

    expect($draft->blocks())->toBe(appearanceDraft()->blocks())
        ->and($result)->toContain($expected)
        ->and($result)->toContain($listed);
})->with([
    'unknown tone' => [['tone' => 'neon'], "'neon' is not a background", 'base, muted, accent, inverted, plain'],
    'unknown spacing' => [['spacing' => 'enormous'], "'enormous' is not a vertical space", 'flush, tight, normal, airy, tall'],
    'unknown width' => [['width' => 'gigantic'], "'gigantic' is not a content width", 'narrow, normal, wide'],
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

it('publishes every axis with the reset sentinel, with guidance on when to use each', function (): void {
    $tool = appearanceTool(appearanceDraft());

    $serialized = json_decode(json_encode(array_map(
        fn ($type): array => $type->toArray(),
        $tool->schema(new JsonSchemaTypeFactory),
    )), associative: true);

    expect(array_keys($serialized))->toBe(['key', 'tone', 'spacing', 'width', 'align', 'columns', 'item_style', 'image_shape'])
        ->and($serialized['key']['type'])->toBe('string')
        ->and($serialized['tone']['enum'])
        ->toBe(['base', 'muted', 'accent', 'inverted', 'plain', 'layout-default'])
        ->and($serialized['spacing']['enum'])
        ->toBe(['flush', 'tight', 'normal', 'airy', 'tall', 'layout-default'])
        ->and($serialized['columns']['enum'])->toBe(['one', 'two', 'three', 'four', 'layout-default'])
        ->and($serialized['item_style']['enum'])->toBe(['plain', 'card', 'outline', 'layout-default'])
        // Descriptions carry the enums' own guidance: five colour names alone
        // leave the model choosing by vibe, and over-use is the real failure.
        ->and($serialized['tone']['description'])->toContain('dramatic')
        ->and($serialized['spacing']['description'])->toContain('the default, and right for most sections')
        ->and($tool->description())->toContain('rhythm')
        // The load-bearing clause: only the mentioned axes change.
        ->and($tool->description())->toContain('Send only the axes you want to change');
});

it('rejects an invalid value for an axis the type does declare', function (): void {
    $draft = appearanceDraft();

    $result = appearanceTool($draft)->handle(new Request(['key' => 'k2', 'columns' => 'five']));

    expect($draft->blocks())->toBe(appearanceDraft()->blocks())
        ->and($result)->toContain("'five' is not a columns")
        ->and($result)->toContain('one, two, three, four');
});

it('sets, keeps and resets the parametric axes independently', function (): void {
    $draft = appearanceDraft();
    $tool = appearanceTool($draft);

    $result = $tool->handle(new Request(['key' => 'k2', 'columns' => 'two', 'item_style' => 'plain']));

    // Stored keys land in LayoutAxis order, whatever order the call used —
    // repeated edits must never reorder the JSON.
    expect($draft->blocks()[1]['data']['appearance'])
        ->toBe(['tone' => 'muted', 'spacing' => 'tight', 'columns' => 'two', 'item_style' => 'plain'])
        ->and($result)->toContain('columns two');

    $tool->handle(new Request(['key' => 'k2', 'columns' => 'layout-default', 'align' => 'start']));

    expect($draft->blocks()[1]['data']['appearance'])
        ->toBe(['tone' => 'muted', 'spacing' => 'tight', 'align' => 'start', 'item_style' => 'plain']);
});

/*
 * Which axes a type takes is the CONTRACT's fact, and the correction must name
 * the real ones — a model told only "no" retries by vibe.
 */
it('refuses an axis the block type never declared, naming the ones it has', function (): void {
    $draft = appearanceDraft();

    $result = appearanceTool($draft)->handle(new Request(['key' => 'k1', 'columns' => 'two']));

    expect($draft->blocks())->toBe(appearanceDraft()->blocks())
        ->and($result)->toContain('columns is not a layout axis of a hero block')
        ->and($result)->toContain("hero block's axes are: tone, spacing, width, align, image_shape");
});
