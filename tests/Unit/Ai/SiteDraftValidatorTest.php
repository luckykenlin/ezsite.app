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

    expect(draftValidator()->handle($draft, 'Fallback')['preset'])->toBe(StylePreset::cases()[0]);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => $message === 'site_draft.preset_fallback')
        ->once();
});

it('de-tags and truncates the meta description', function (): void {
    $draft = validAiDraft();
    $draft['pages'][0]['meta_description'] = '  <b>Fresh sourdough</b> baked daily in Austin. '.str_repeat('More words. ', 30);

    $description = draftValidator()->handle($draft, 'Fallback')['pages'][0]['metaDescription'];

    expect($description)->toStartWith('Fresh sourdough baked daily in Austin.')
        ->and(mb_strlen((string) $description))->toBe(160);
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
