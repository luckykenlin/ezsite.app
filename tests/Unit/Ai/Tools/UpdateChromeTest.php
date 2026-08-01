<?php

declare(strict_types=1);

use App\Ai\BlockDataSanitizer;
use App\Ai\SiteChromeDraft;
use App\Ai\Tools\UpdateChrome;
use App\Enums\ChromeSlot;
use App\Site\Blocks\BlockVocabulary;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

function chromeTool(SiteChromeDraft $draft): UpdateChrome
{
    return new UpdateChrome($draft, resolve(BlockVocabulary::class), resolve(BlockDataSanitizer::class));
}

function editableChrome(): SiteChromeDraft
{
    return new SiteChromeDraft([
        'header' => ['type' => 'header', 'data' => [
            'variant' => 'simple',
            'nav_links' => [['label' => 'Home', 'url' => '/']],
            'cta_label' => 'Call us',
        ]],
        'footer' => ['type' => 'footer', 'data' => ['variant' => 'minimal', 'note' => 'Open daily.']],
    ]);
}

/*
 * The request this tool exists for. "Add Services to the menu" was the most
 * natural thing to ask a website builder and the one thing the prompt told the
 * model outright it could not do.
 */
it('adds a link to the navigation, keeping the fields it was not sent', function (): void {
    $draft = editableChrome();

    $result = chromeTool($draft)->handle(new Request([
        'slot' => 'header',
        'content' => ['nav_links' => [
            ['label' => 'Home', 'url' => '/'],
            ['label' => 'Services', 'url' => '/services'],
        ]],
    ]));

    $data = $draft->current(ChromeSlot::Header)['data'];

    expect($data['nav_links'])->toHaveCount(2)
        // Merged, so an unsent field survives.
        ->and($data['cta_label'])->toBe('Call us')
        // Reserved keys are server-owned and are carried through the merge.
        ->and($data['variant'])->toBe('simple')
        // The answer has to say it is site-wide: an operator who asked about
        // "this page" needs telling that is not what they changed.
        ->and($result)->toContain('every page of the site');
});

it('switches a slot to another layout it offers, without touching its content', function (): void {
    $draft = editableChrome();

    $result = chromeTool($draft)->handle(new Request(['slot' => 'header', 'variant' => 'centered']));

    expect($draft->current(ChromeSlot::Header)['data']['variant'])->toBe('centered')
        ->and($draft->current(ChromeSlot::Header)['data']['cta_label'])->toBe('Call us')
        ->and($result)->toContain('layout');
});

it('changes content and layout in one call', function (): void {
    $draft = editableChrome();

    chromeTool($draft)->handle(new Request([
        'slot' => 'footer',
        'variant' => 'columns',
        'content' => ['note' => 'Closed Sundays.'],
    ]));

    expect($draft->current(ChromeSlot::Footer)['data'])
        ->toBe(['variant' => 'columns', 'note' => 'Closed Sundays.']);
});

/*
 * The validation is NOT merged just because the tool is. A layout is checked
 * against the slot's own options exactly as SetBlockVariant checks a block's —
 * the schema enum is the union of both slots', so `columns` is a legal argument
 * that a header still must refuse.
 */
it('refuses a layout the other slot offers but this one does not', function (): void {
    $draft = editableChrome();

    $result = chromeTool($draft)->handle(new Request(['slot' => 'header', 'variant' => 'columns']));

    expect($draft->toArray())->toBeNull()
        ->and($result)->toContain("The header has no 'columns' layout")
        ->and($result)->toContain('simple, centered');
});

/*
 * The other half of "one tool, two doors": content goes through the sanitizer,
 * so the model cannot write a layout by calling it a content field.
 */
it('strips a reserved key smuggled in as content', function (): void {
    $draft = editableChrome();

    chromeTool($draft)->handle(new Request([
        'slot' => 'header',
        'content' => ['variant' => 'centered', 'cta_label' => 'Book now'],
    ]));

    expect($draft->current(ChromeSlot::Header)['data']['variant'])->toBe('simple')
        ->and($draft->current(ChromeSlot::Header)['data']['cta_label'])->toBe('Book now');
});

it('rejects an unknown slot and names the real ones', function (mixed $slot): void {
    $draft = editableChrome();

    $result = chromeTool($draft)->handle(new Request(['slot' => $slot, 'content' => ['note' => 'Hi']]));

    expect($draft->toArray())->toBeNull()
        ->and($result)->toContain('The site chrome slots are: header, footer');
})->with([
    'unknown' => ['sidebar'],
    'missing' => [null],
]);

it('says nothing changed when no field name was real', function (): void {
    $draft = editableChrome();

    $result = chromeTool($draft)->handle(new Request([
        'slot' => 'header',
        'content' => ['tagline' => 'Not a header field'],
    ]));

    expect($draft->toArray())->toBeNull()
        ->and($result)->toContain('None of those field names exist on the header');
});

it('requires either content or a layout', function (): void {
    $draft = editableChrome();

    $result = chromeTool($draft)->handle(new Request(['slot' => 'footer']));

    expect($draft->toArray())->toBeNull()
        ->and($result)->toContain('send the fields to change, a layout, or both');
});

it('treats empty content as nothing to do', function (): void {
    $draft = editableChrome();

    $result = chromeTool($draft)->handle(new Request(['slot' => 'footer', 'content' => []]));

    expect($draft->toArray())->toBeNull()
        ->and($result)->toContain('send the fields to change, a layout, or both');
});

it('publishes both slots and every chrome layout in its schema', function (): void {
    $tool = chromeTool(editableChrome());

    $serialized = json_decode(json_encode(array_map(
        fn ($type): array => $type->toArray(),
        $tool->schema(new JsonSchemaTypeFactory),
    )), associative: true);

    expect($serialized['slot']['enum'])->toBe(['header', 'footer'])
        ->and($serialized['variant']['enum'])->toBe(['simple', 'centered', 'columns', 'minimal'])
        // The load-bearing warning: nav_links is replaced whole, so a model that
        // sends one link deletes the rest of the navigation.
        ->and($serialized['content']['description'])->toContain('REPLACED whole')
        ->and($tool->description())->toContain('EVERY page');
});
