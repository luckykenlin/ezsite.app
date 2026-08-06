<?php

declare(strict_types=1);

use App\Actions\Pages\UpdatePageBlock;
use App\Ai\PageDraft;
use App\Ai\Tools\SetBlockImage;
use App\Models\Media;
use App\Models\Tenant;
use App\Site\Blocks\BlockVocabulary;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

function toolMedia(): Media
{
    $tenant = Tenant::factory()->create();

    // Created inside a tenant (RLS write guard) and read back as the
    // superuser, exactly as the worker-side tool reads it inside a turn.
    return test()->runInTenant($tenant, fn (): Media => Media::factory()->create(['tenant_id' => $tenant->id]));
}

function imageTool(PageDraft $draft): SetBlockImage
{
    return new SetBlockImage($draft, resolve(BlockVocabulary::class), resolve(UpdatePageBlock::class));
}

function imageDraft(): PageDraft
{
    return new PageDraft([
        ['key' => 'k1', 'type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Hello']],
        // Repeater items are uuid-keyed, exactly as Filament dehydrates them.
        ['key' => 'k2', 'type' => 'gallery', 'data' => ['heading' => 'Work', 'images' => [
            'uuid-a' => ['alt' => 'One'],
            'uuid-b' => ['alt' => 'Two'],
        ]]],
        ['key' => 'k3', 'type' => 'heading', 'data' => ['content' => 'Plain text']],
    ]);
}

it('places a media-library image on a block-level slot', function (): void {
    $media = toolMedia();
    $draft = imageDraft();

    $result = imageTool($draft)->handle(new Request(['key' => 'k1', 'media_id' => (int) $media->id]));

    expect($draft->blocks()[0]['data']['image_id'])->toBe((int) $media->id)
        // Reserved keys and content survive: the tool writes one field.
        ->and($draft->blocks()[0]['data']['variant'])->toBe('centered-minimal')
        ->and($draft->blocks()[0]['data']['heading'])->toBe('Hello')
        ->and($result)->toContain('Placed media '.$media->id.' on the hero block');
});

it('places an image on one repeater item, addressed by position', function (): void {
    $media = toolMedia();
    $draft = imageDraft();

    imageTool($draft)->handle(new Request([
        'key' => 'k2',
        'media_id' => (int) $media->id,
        'item_index' => 1,
        'alt' => 'The dining room at dusk',
    ]));

    $items = $draft->blocks()[1]['data']['images'];

    expect($items['uuid-b']['media_id'])->toBe((int) $media->id)
        ->and($items['uuid-b']['alt'])->toBe('The dining room at dusk')
        // The neighbouring item is untouched.
        ->and($items['uuid-a'])->toBe(['alt' => 'One']);
});

it('rebuilds a malformed repeater item rather than writing into a scalar', function (): void {
    $media = toolMedia();
    $draft = new PageDraft([
        ['key' => 'k2', 'type' => 'gallery', 'data' => ['images' => ['uuid-a' => 'not an item']]],
    ]);

    imageTool($draft)->handle(new Request(['key' => 'k2', 'media_id' => (int) $media->id, 'item_index' => 0]));

    expect($draft->blocks()[0]['data']['images']['uuid-a'])->toBe(['media_id' => (int) $media->id]);
});

it('asks for an item index when the block keeps images on its items', function (): void {
    $media = toolMedia();
    $draft = imageDraft();

    $result = imageTool($draft)->handle(new Request(['key' => 'k2', 'media_id' => (int) $media->id]));

    expect($draft->blocks())->toBe(imageDraft()->blocks())
        ->and($result)->toContain('pass item_index');
});

it('rejects an item index the block does not have', function (): void {
    $media = toolMedia();
    $draft = imageDraft();

    $result = imageTool($draft)->handle(new Request(['key' => 'k2', 'media_id' => (int) $media->id, 'item_index' => 5]));

    expect($draft->blocks())->toBe(imageDraft()->blocks())
        ->and($result)->toContain('has 2 items')
        ->and($result)->toContain('item_index 5 does not exist');
});

it('refuses a block type with no image slot', function (): void {
    $media = toolMedia();
    $draft = imageDraft();

    $result = imageTool($draft)->handle(new Request(['key' => 'k3', 'media_id' => (int) $media->id]));

    expect($draft->blocks())->toBe(imageDraft()->blocks())
        ->and($result)->toContain("A 'heading' block has no image slot");
});

/*
 * The one thing the model authors here is the media id, so an id that is not
 * in the library — invented, or another tenant's (RLS makes those identical) —
 * must be a correction, never a dangling reference on the page.
 */
it('refuses a media id that is not in the library', function (mixed $mediaId): void {
    $draft = imageDraft();

    $result = imageTool($draft)->handle(new Request(['key' => 'k1', 'media_id' => $mediaId]));

    expect($draft->blocks())->toBe(imageDraft()->blocks())
        ->and($result)->toContain('no media with that id');
})->with([
    'unknown id' => [999999],
    'not an integer' => ['42'],
    'missing' => [null],
]);

it('rejects a key that is not on the page', function (): void {
    $draft = imageDraft();

    $result = imageTool($draft)->handle(new Request(['key' => 'ghost', 'media_id' => 1]));

    expect($draft->blocks())->toBe(imageDraft()->blocks())
        ->and($result)->toContain('no block with that key');
});

it('tells the model backgrounds are this tool plus a variant switch', function (): void {
    $schema = imageTool(imageDraft())->schema(new JsonSchemaTypeFactory);

    expect(array_keys($schema))->toBe(['key', 'media_id', 'item_index', 'alt'])
        // The two load-bearing clauses: never invent a media id, and a
        // photographic background is image + layout, not a new tool.
        ->and(imageTool(imageDraft())->description())->toContain('Never invent a media id')
        ->and(imageTool(imageDraft())->description())->toContain('set block variant tool');
});
