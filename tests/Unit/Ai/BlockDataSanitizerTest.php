<?php

declare(strict_types=1);

use App\Ai\BlockDataSanitizer;
use Illuminate\Support\Facades\Log;

/*
 * The shared "what may the AI write" gate. Both write paths delegate here —
 * whole-site generation (SiteDraftValidator) and the editor chat
 * (Ai\Tools\UpdateBlockContent) — so these rules are asserted once, under the
 * primitive's own name, rather than restated per consumer.
 */

function sanitizer(): BlockDataSanitizer
{
    return resolve(BlockDataSanitizer::class);
}

it('keeps the fields a block type declares', function (): void {
    $clean = sanitizer()->handle('hero', ['heading' => 'Welcome friends', 'subheading' => 'Fresh daily']);

    expect($clean)->toBe(['heading' => 'Welcome friends', 'subheading' => 'Fresh daily']);
});

it('strips unknown data fields and the server-owned reserved keys', function (): void {
    Log::spy();

    $clean = sanitizer()->handle('hero', [
        'heading' => 'Welcome friends',
        'variant' => 'full-bleed-overlay', // server-owned
        'bind' => ['location_id' => 99],   // server-owned
        'made_up_field' => 'nope',
    ]);

    expect($clean)->toBe(['heading' => 'Welcome friends']);

    // Reserved keys are stripped silently; only the unknown field warns.
    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $message === 'ai_block.field_stripped'
            && $context['field'] === 'made_up_field')
        ->once();
});

it('strips everything for a block type that is not in the vocabulary', function (): void {
    Log::spy();

    expect(sanitizer()->handle('ghost', ['heading' => 'Boo']))->toBeEmpty();
});

it('reports which block types it knows', function (): void {
    expect(sanitizer()->knows('hero'))->toBeTrue()
        ->and(sanitizer()->knows('ghost'))->toBeFalse();
});

it('de-tags every string and sanitizes repeater items down one level', function (): void {
    $hero = sanitizer()->handle('hero', ['heading' => '<script>alert(1)</script>Hi']);

    $features = sanitizer()->handle('features', [
        'features' => [
            ['title' => '<b>Bold</b> move', 'weight' => 3, 'nested' => ['too' => 'deep']],
            'not an item',
        ],
    ]);

    expect($hero['heading'])->toBe('alert(1)Hi')
        ->and($features['features'])->toBe([['title' => 'Bold move', 'weight' => 3]]);
});

it('drops a repeater whose every item is unusable', function (): void {
    expect(sanitizer()->handle('features', ['features' => ['not an item', 42]]))
        ->not->toHaveKey('features');
});

it('drops non-scalar leaf values', function (mixed $leaf): void {
    expect(sanitizer()->handle('hero', ['heading' => $leaf]))->not->toHaveKey('heading');
})->with([
    'nested map' => [['unexpected' => 'shape']],
    'null' => [null],
]);

it('passes non-string scalars through untouched', function (): void {
    expect(sanitizer()->handle('hero', ['heading' => 42])['heading'])->toBe(42);
});

it('normalizes heading levels the model writes as bare numbers', function (mixed $stored, ?string $expected): void {
    $clean = sanitizer()->handle('heading', ['content' => 'Services', 'level' => $stored]);

    if ($expected === null) {
        expect($clean)->not->toHaveKey('level');
    } else {
        expect($clean['level'])->toBe($expected);
    }
})->with([
    'bare number string' => ['2', 'h2'],
    'integer' => [3, 'h3'],
    'uppercase' => ['H4', 'h4'],
    'already valid' => ['h5', 'h5'],
    'unmappable' => ['x9', null],
    'non-stringable scalar' => [true, null],
]);

it('leaves a heading with no level alone', function (): void {
    expect(sanitizer()->handle('heading', ['content' => 'Services']))->toBe(['content' => 'Services']);
});

it('warns about a non-string data key', function (): void {
    Log::spy();

    expect(sanitizer()->handle('hero', [0 => 'positional']))->toBeEmpty();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $message === 'ai_block.field_stripped'
            && $context['field'] === 0)
        ->once();
});
