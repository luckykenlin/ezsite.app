<?php

declare(strict_types=1);

use App\Ai\SiteDraftValidator;
use App\Design\StylePreset;
use App\Exceptions\SiteDraftUnusable;
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
        ->and($result['pages'][0]['title'])->toBe('Home')
        ->and(array_column($result['pages'][0]['blocks'], 'type'))->toBe(['hero', 'features', 'cta'])
        ->and($result['pages'][0]['blocks'][1]['data']['features'][0]['title'])->toBe('Handmade');
});

it('falls back to the first preset when the stored key is unknown', function (): void {
    Log::spy();

    $draft = validAiDraft();
    $draft['preset'] = 'no-such-preset';

    // Named, not `StylePreset::cases()[0]` — production returns exactly that, so
    // restating it would survive a reordering that silently changes the fallback.
    expect(draftValidator()->handle($draft, 'Fallback')['preset'])->toBe(StylePreset::WarmCraft);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => $message === 'site_draft.preset_fallback')
        ->once();
});

it('de-tags and truncates the meta description', function (): void {
    $draft = validAiDraft();
    $draft['pages'][0]['meta_description'] = '  <b>Fresh sourdough</b> baked daily in Austin. '.str_repeat('More words. ', 30);

    $description = draftValidator()->handle($draft, 'Fallback')['pages'][0]['metaDescription'];

    expect($description)->toStartWith('Fresh sourdough baked daily in Austin.')
        ->and((string) $description)->toHaveLength(160);
});

it('accepts a draft with no meta description at all', function (): void {
    $draft = validAiDraft();
    $draft['pages'][0]['meta_description'] = '   ';

    expect(draftValidator()->handle($draft, 'Fallback')['pages'][0]['metaDescription'])->toBeNull()
        ->and(draftValidator()->handle(validAiDraft(), 'Fallback')['pages'][0]['metaDescription'])->toBeNull();
});

it('falls back to the business name when the title is blank', function (): void {
    $draft = validAiDraft();
    $draft['pages'][0]['title'] = '  ';

    expect(draftValidator()->handle($draft, 'Corner Cafe')['pages'][0]['title'])->toBe('Corner Cafe');
});

it('drops unknown and chrome block types with a warning', function (): void {
    Log::spy();

    $draft = validAiDraft();
    $draft['pages'][0]['blocks'][] = ['type' => 'ghost', 'data' => []];
    $draft['pages'][0]['blocks'][] = ['type' => 'header', 'data' => []];
    $draft['pages'][0]['blocks'][] = 'not even an array';

    $result = draftValidator()->handle($draft, 'Fallback');

    expect(array_column($result['pages'][0]['blocks'], 'type'))->toBe(['hero', 'features', 'cta']);

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

    expect(array_column($result['pages'][0]['blocks'], 'type'))->toBe(['hero', 'features', 'cta']);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $message === 'site_draft.block_dropped'
            && $context['reason'] === 'duplicate_hero')
        ->once();
});

/*
 * The layout keys — the draft path's validating door onto variant/tone/spacing,
 * the counterpart of SetBlockVariant/SetBlockAppearance on the chat path. Only
 * enum-checked values reach `data`; anything else falls through to the preset
 * fill, with a log so provider drift stays measurable.
 */
it('validates block-level layout choices into data, block level winning over the data salvage', function (): void {
    $draft = validAiDraft();
    $draft['pages'][0]['blocks'][0]['variant'] = 'centered-minimal';
    $draft['pages'][0]['blocks'][0]['data']['variant'] = 'full-bleed-overlay';
    $draft['pages'][0]['blocks'][1]['tone'] = 'muted';
    $draft['pages'][0]['blocks'][1]['spacing'] = 'airy';

    $blocks = draftValidator()->handle($draft, 'Fallback')['pages'][0]['blocks'];

    expect($blocks[0]['data']['variant'])->toBe('centered-minimal')
        ->and($blocks[1]['data']['appearance'])->toBe(['tone' => 'muted', 'spacing' => 'airy'])
        // A block that chose nothing carries nothing — the preset fill decides later.
        ->and($blocks[2]['data'])->not->toHaveKey('variant')
        ->and($blocks[2]['data'])->not->toHaveKey('appearance');
});

it('salvages layout choices the model tucked inside data', function (): void {
    // Prompt-enforced structured output drifts: providers habitually nest the
    // keys inside data, where the sanitizer strips them. The salvage read is free.
    $draft = validAiDraft();
    $draft['pages'][0]['blocks'][0]['data']['variant'] = 'full-bleed-overlay';
    $draft['pages'][0]['blocks'][1]['data']['appearance'] = ['tone' => 'inverted', 'spacing' => 'tight'];

    $blocks = draftValidator()->handle($draft, 'Fallback')['pages'][0]['blocks'];

    expect($blocks[0]['data']['variant'])->toBe('full-bleed-overlay')
        ->and($blocks[1]['data']['appearance'])->toBe(['tone' => 'inverted', 'spacing' => 'tight']);
});

it('drops layout choices outside the enums, logging each dimension', function (): void {
    Log::spy();

    $draft = validAiDraft();
    // A real variant, but of the wrong type: hero offers no 'grid'.
    $draft['pages'][0]['blocks'][0]['variant'] = 'grid';
    $draft['pages'][0]['blocks'][1]['tone'] = 'neon';
    $draft['pages'][0]['blocks'][1]['spacing'] = 'huge';

    $blocks = draftValidator()->handle($draft, 'Fallback')['pages'][0]['blocks'];

    expect($blocks[0]['data'])->not->toHaveKey('variant')
        ->and($blocks[1]['data'])->not->toHaveKey('appearance');

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message, array $context): bool => $message === 'site_draft.layout_dropped'
            && in_array($context['dimension'], ['variant', 'tone', 'spacing'], true))
        ->times(3);
});

it('harvests image queries into the reserved transit key when the pipeline is enabled', function (): void {
    config()->set('stock-photos.enabled', true);

    $draft = validAiDraft();
    $draft['pages'][0]['blocks'][0]['data']['image_query'] = '  <b>barber shop</b> interior '.str_repeat('x', 100);
    // The reserved key itself can never be authored directly — the sanitizer
    // strips it, whatever either AI path sends.
    $draft['pages'][0]['blocks'][1]['data']['_image_query'] = 'smuggled';

    $blocks = draftValidator()->handle($draft, 'Fallback')['pages'][0]['blocks'];

    expect($blocks[0]['data']['_image_query'])->toStartWith('barber shop interior')
        ->and($blocks[0]['data']['_image_query'])->toHaveLength(80)
        ->and($blocks[0]['data'])->not->toHaveKey('image_query')
        ->and($blocks[1]['data'])->not->toHaveKey('_image_query');
});

it('discards image queries when the pipeline is disabled', function (): void {
    $draft = validAiDraft();
    $draft['pages'][0]['blocks'][0]['data']['image_query'] = 'barber shop interior';

    $blocks = draftValidator()->handle($draft, 'Fallback')['pages'][0]['blocks'];

    // Nothing will ever consume the key, so persisting it would litter every
    // generated page with a transit slot for a pipeline that is off.
    expect($blocks[0]['data'])->not->toHaveKey('_image_query')
        ->and($blocks[0]['data'])->not->toHaveKey('image_query');
});

it('rejects a draft that survives with too few blocks', function (): void {
    $draft = validAiDraft();
    $draft['pages'][0]['blocks'] = array_slice($draft['pages'][0]['blocks'], 0, 2);

    expect(fn (): array => draftValidator()->handle($draft, 'Fallback'))
        ->toThrow(SiteDraftUnusable::class, '2 block(s) survived');
});

it('rejects a draft without a hero', function (): void {
    $draft = validAiDraft();
    $draft['pages'][0]['blocks'][0]['type'] = 'testimonials';

    expect(fn (): array => draftValidator()->handle($draft, 'Fallback'))
        ->toThrow(SiteDraftUnusable::class, 'no hero');
});

it('rejects an entirely missing page', function (): void {
    expect(fn (): array => draftValidator()->handle(['preset' => 'warm-craft', 'pages' => 'oops'], 'Fallback'))
        ->toThrow(SiteDraftUnusable::class);
});

/*
 * Multi-page drafts. The home page keeps the hard bar (a failure there fails
 * the whole draft, retryably); an extra page that does not survive is DROPPED
 * — a thin /about must not cost the operator the home page.
 */
function extraPage(string $slug, string $title = 'About Us'): array
{
    return [
        'title' => $title,
        'slug' => $slug,
        'blocks' => [
            ['type' => 'heading', 'data' => ['content' => 'Our story', 'level' => 'h2']],
            ['type' => 'prose', 'data' => ['body' => 'We bake.']],
            ['type' => 'cta', 'data' => ['heading' => 'Come by', 'cta_label' => 'Visit', 'cta_url' => '/contact']],
        ],
    ];
}

it('accepts extra pages from the slug menu, home first', function (): void {
    $draft = validAiDraft();
    // The model may answer in any order; the result leads with home.
    array_unshift($draft['pages'], extraPage('/about'));

    $pages = draftValidator()->handle($draft, 'Fallback')['pages'];

    expect(array_column($pages, 'slug'))->toBe(['/', '/about'])
        ->and($pages[1]['title'])->toBe('About Us')
        // Extra pages need no hero — the bar is substance, not shape.
        ->and(array_column($pages[1]['blocks'], 'type'))->toBe(['heading', 'prose', 'cta']);
});

it('titles an untitled extra page from its slug', function (): void {
    $draft = validAiDraft();
    $draft['pages'][] = extraPage('/services', '   ');

    expect(draftValidator()->handle($draft, 'Fallback')['pages'][1]['title'])->toBe('Services');
});

it('drops extra pages with unknown or duplicate slugs, or too little substance', function (): void {
    Log::spy();

    $draft = validAiDraft();
    $draft['pages'][] = extraPage('/pricing');                    // not on the menu
    $draft['pages'][] = extraPage('/about');
    $draft['pages'][] = extraPage('/about', 'Second About');      // duplicate
    $thin = extraPage('/contact');
    $thin['blocks'] = array_slice($thin['blocks'], 0, 2);
    $draft['pages'][] = $thin;                                    // below MIN_BLOCKS
    $draft['pages'][] = 'not a page at all';

    $pages = draftValidator()->handle($draft, 'Fallback')['pages'];

    expect(array_column($pages, 'slug'))->toBe(['/', '/about']);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => $message === 'site_draft.page_dropped')
        ->times(4);
});

it('rejects a draft whose pages carry no home at all', function (): void {
    expect(fn (): array => draftValidator()->handle([
        'preset' => 'warm-craft',
        'pages' => [extraPage('/about')],
    ], 'Fallback'))->toThrow(SiteDraftUnusable::class, 'no home page');
});

it('accepts the parametric axes at block level and writes them into appearance', function (): void {
    $draft = validAiDraft();
    $draft['pages'][0]['blocks'][1]['columns'] = 'two';
    $draft['pages'][0]['blocks'][1]['item_style'] = 'plain';
    $draft['pages'][0]['blocks'][1]['width'] = 'narrow';

    $blocks = draftValidator()->handle($draft, 'Fallback')['pages'][0]['blocks'];

    expect($blocks[1]['data']['appearance'])
        ->toBe(['width' => 'narrow', 'columns' => 'two', 'item_style' => 'plain']);
});

/*
 * An axis the type never declared degrades exactly like a typo — dropped and
 * logged — so "the model keeps putting columns on heroes" is visible in the
 * same telemetry as "the model chose garbage".
 */
it('drops and logs an axis the block type does not declare', function (): void {
    Log::spy();

    $draft = validAiDraft();
    $draft['pages'][0]['blocks'][0]['columns'] = 'two';

    $blocks = draftValidator()->handle($draft, 'Fallback')['pages'][0]['blocks'];

    expect($blocks[0]['data'])->not->toHaveKey('appearance');

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message, array $context): bool => $message === 'site_draft.layout_dropped'
            && $context['dimension'] === 'columns'
            && $context['type'] === 'hero'
            && ($context['reason'] ?? null) === 'axis_not_applicable')
        ->once();
});
