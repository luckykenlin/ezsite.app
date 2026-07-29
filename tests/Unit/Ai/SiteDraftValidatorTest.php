<?php

declare(strict_types=1);

use App\Ai\SiteDraftValidator;
use App\Design\StylePreset;
use App\Exceptions\SiteDraftInvalid;
use Illuminate\Support\Facades\Log;

function draftValidator(): SiteDraftValidator
{
    return resolve(SiteDraftValidator::class);
}

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
    $result = draftValidator()->handle(validAiDraft(), 'Fallback');

    expect($result['preset'])->toBe(StylePreset::WarmCraft)
        ->and($result['title'])->toBe('Home')
        ->and(array_column($result['blocks'], 'type'))->toBe(['hero', 'features', 'cta'])
        ->and($result['blocks'][1]['data']['features'][0]['title'])->toBe('Handmade');
});

it('falls back to the first preset when the stored key is unknown', function (): void {
    Log::spy();

    $draft = validAiDraft();
    $draft['preset'] = 'no-such-preset';

    expect(draftValidator()->handle($draft, 'Fallback')['preset'])->toBe(StylePreset::cases()[0]);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => $message === 'site_draft.preset_fallback')
        ->once();
});

it('de-tags and truncates the meta description', function (): void {
    $draft = validAiDraft();
    $draft['pages'][0]['meta_description'] = '  <b>Fresh sourdough</b> baked daily in Austin. '.str_repeat('More words. ', 30);

    $description = draftValidator()->handle($draft, 'Fallback')['metaDescription'];

    expect($description)->toStartWith('Fresh sourdough baked daily in Austin.')
        ->and(mb_strlen((string) $description))->toBe(160);
});

it('accepts a draft with no meta description at all', function (): void {
    $draft = validAiDraft();
    $draft['pages'][0]['meta_description'] = '   ';

    expect(draftValidator()->handle($draft, 'Fallback')['metaDescription'])->toBeNull()
        ->and(draftValidator()->handle(validAiDraft(), 'Fallback')['metaDescription'])->toBeNull();
});

it('falls back to the business name when the title is blank', function (): void {
    $draft = validAiDraft();
    $draft['pages'][0]['title'] = '  ';

    expect(draftValidator()->handle($draft, 'Corner Cafe')['title'])->toBe('Corner Cafe');
});

it('drops unknown and chrome block types with a warning', function (): void {
    Log::spy();

    $draft = validAiDraft();
    $draft['pages'][0]['blocks'][] = ['type' => 'ghost', 'data' => []];
    $draft['pages'][0]['blocks'][] = ['type' => 'header', 'data' => []];
    $draft['pages'][0]['blocks'][] = 'not even an array';

    $result = draftValidator()->handle($draft, 'Fallback');

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

    $result = draftValidator()->handle($draft, 'Fallback');

    expect(array_column($result['blocks'], 'type'))->toBe(['hero', 'features', 'cta']);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $message === 'site_draft.block_dropped'
            && $context['reason'] === 'duplicate_hero')
        ->once();
});

it('rejects a draft that survives with too few blocks', function (): void {
    $draft = validAiDraft();
    $draft['pages'][0]['blocks'] = array_slice($draft['pages'][0]['blocks'], 0, 2);

    expect(fn (): array => draftValidator()->handle($draft, 'Fallback'))
        ->toThrow(SiteDraftInvalid::class, '2 block(s) survived');
});

it('rejects a draft without a hero', function (): void {
    $draft = validAiDraft();
    $draft['pages'][0]['blocks'][0]['type'] = 'testimonials';

    expect(fn (): array => draftValidator()->handle($draft, 'Fallback'))
        ->toThrow(SiteDraftInvalid::class, 'no hero');
});

it('rejects an entirely missing page', function (): void {
    expect(fn (): array => draftValidator()->handle(['preset' => 'warm-craft', 'pages' => 'oops'], 'Fallback'))
        ->toThrow(SiteDraftInvalid::class);
});
