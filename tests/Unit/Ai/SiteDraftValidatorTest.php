<?php

declare(strict_types=1);

use App\Ai\SiteDraftValidator;
use App\Design\StylePreset;
use App\Exceptions\SiteDraftInvalid;
use Illuminate\Support\Facades\Log;

function validAiDraft(): array
{
    return [
        'preset' => 'warm-craft',
        'rationale' => 'Earthy tones match a bakery.',
        'pages' => [[
            'title' => 'Home',
            'slug' => '/',
            'blocks' => [
                ['type' => 'hero', 'data' => ['heading' => 'Welcome friends', 'subheading' => 'Fresh daily']],
                ['type' => 'features', 'data' => ['heading' => 'Why us', 'features' => [
                    ['icon' => '⭐', 'title' => 'Handmade', 'description' => 'Small batches'],
                ]]],
                ['type' => 'cta', 'data' => ['heading' => 'Come by', 'cta_label' => 'Visit', 'cta_url' => '/contact']],
            ],
        ]],
    ];
}

it('passes a clean draft through unchanged', function (): void {
    $result = new SiteDraftValidator()->handle(validAiDraft(), 'Fallback');

    expect($result['preset'])->toBe(StylePreset::WarmCraft)
        ->and($result['title'])->toBe('Home')
        ->and(array_column($result['blocks'], 'type'))->toBe(['hero', 'features', 'cta'])
        ->and($result['blocks'][1]['data']['features'][0]['title'])->toBe('Handmade');
});

it('falls back to the first preset when the stored key is unknown', function (): void {
    Log::spy();

    $draft = validAiDraft();
    $draft['preset'] = 'no-such-preset';

    expect(new SiteDraftValidator()->handle($draft, 'Fallback')['preset'])->toBe(StylePreset::cases()[0]);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => $message === 'site_draft.preset_fallback')
        ->once();
});

it('falls back to the business name when the title is blank', function (): void {
    $draft = validAiDraft();
    $draft['pages'][0]['title'] = '  ';

    expect(new SiteDraftValidator()->handle($draft, 'Corner Cafe')['title'])->toBe('Corner Cafe');
});

it('drops unknown and chrome block types with a warning', function (): void {
    Log::spy();

    $draft = validAiDraft();
    $draft['pages'][0]['blocks'][] = ['type' => 'ghost', 'data' => []];
    $draft['pages'][0]['blocks'][] = ['type' => 'header', 'data' => []];
    $draft['pages'][0]['blocks'][] = 'not even an array';

    $result = new SiteDraftValidator()->handle($draft, 'Fallback');

    expect(array_column($result['blocks'], 'type'))->toBe(['hero', 'features', 'cta']);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $message === 'site_draft.block_dropped'
            && $context['reason'] === 'unknown_or_chrome_type')
        ->times(3);
});

it('drops a duplicate hero', function (): void {
    Log::spy();

    $draft = validAiDraft();
    $draft['pages'][0]['blocks'][] = ['type' => 'hero', 'data' => ['heading' => 'Second hero']];

    $result = new SiteDraftValidator()->handle($draft, 'Fallback');

    expect(array_column($result['blocks'], 'type'))->toBe(['hero', 'features', 'cta']);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $message === 'site_draft.block_dropped'
            && $context['reason'] === 'duplicate_hero')
        ->once();
});

it('strips unknown data fields and the server-owned reserved keys', function (): void {
    Log::spy();

    $draft = validAiDraft();
    $draft['pages'][0]['blocks'][0]['data']['variant'] = 'full-bleed-overlay'; // server-owned
    $draft['pages'][0]['blocks'][0]['data']['bind'] = ['location_id' => 99]; // server-owned
    $draft['pages'][0]['blocks'][0]['data']['made_up_field'] = 'nope';

    $hero = new SiteDraftValidator()->handle($draft, 'Fallback')['blocks'][0];

    expect($hero['data'])->not->toHaveKeys(['variant', 'bind', 'made_up_field'])
        ->and($hero['data']['heading'])->toBe('Welcome friends');

    // Reserved keys are stripped silently; only the unknown field warns.
    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $message === 'site_draft.field_stripped'
            && $context['field'] === 'made_up_field')
        ->once();
});

it('de-tags every string and sanitizes repeater items down one level', function (): void {
    $draft = validAiDraft();
    $draft['pages'][0]['blocks'][0]['data']['heading'] = '<script>alert(1)</script>Hi';
    $draft['pages'][0]['blocks'][1]['data']['features'] = [
        ['title' => '<b>Bold</b> move', 'weight' => 3, 'nested' => ['too' => 'deep']],
        'not an item',
    ];

    $result = new SiteDraftValidator()->handle($draft, 'Fallback');

    expect($result['blocks'][0]['data']['heading'])->toBe('alert(1)Hi')
        ->and($result['blocks'][1]['data']['features'])->toBe([['title' => 'Bold move', 'weight' => 3]]);
});

it('drops non-scalar leaf values', function (mixed $leaf): void {
    $draft = validAiDraft();
    $draft['pages'][0]['blocks'][0]['data']['heading'] = $leaf;

    expect(new SiteDraftValidator()->handle($draft, 'Fallback')['blocks'][0]['data'])
        ->not->toHaveKey('heading');
})->with([
    'nested map' => [['unexpected' => 'shape']],
    'null' => [null],
]);

it('normalizes heading levels the model writes as bare numbers', function (mixed $stored, ?string $expected): void {
    $draft = validAiDraft();
    $draft['pages'][0]['blocks'][] = ['type' => 'heading', 'data' => ['content' => 'Services', 'level' => $stored]];

    $heading = new SiteDraftValidator()->handle($draft, 'Fallback')['blocks'][3];

    if ($expected === null) {
        expect($heading['data'])->not->toHaveKey('level');
    } else {
        expect($heading['data']['level'])->toBe($expected);
    }
})->with([
    'bare number string' => ['2', 'h2'],
    'integer' => [3, 'h3'],
    'uppercase' => ['H4', 'h4'],
    'already valid' => ['h5', 'h5'],
    'unmappable' => ['x9', null],
    'non-stringable scalar' => [true, null],
]);

it('passes non-string scalars through untouched', function (): void {
    $draft = validAiDraft();
    $draft['pages'][0]['blocks'][0]['data']['heading'] = 42;

    expect(new SiteDraftValidator()->handle($draft, 'Fallback')['blocks'][0]['data']['heading'])->toBe(42);
});

it('rejects a draft that survives with too few blocks', function (): void {
    $draft = validAiDraft();
    $draft['pages'][0]['blocks'] = array_slice($draft['pages'][0]['blocks'], 0, 2);

    expect(fn (): array => new SiteDraftValidator()->handle($draft, 'Fallback'))
        ->toThrow(SiteDraftInvalid::class, '2 block(s) survived');
});

it('rejects a draft without a hero', function (): void {
    $draft = validAiDraft();
    $draft['pages'][0]['blocks'][0]['type'] = 'testimonials';

    expect(fn (): array => new SiteDraftValidator()->handle($draft, 'Fallback'))
        ->toThrow(SiteDraftInvalid::class, 'no hero');
});

it('rejects an entirely missing page', function (): void {
    expect(fn (): array => new SiteDraftValidator()->handle(['preset' => 'warm-craft', 'pages' => 'oops'], 'Fallback'))
        ->toThrow(SiteDraftInvalid::class);
});
