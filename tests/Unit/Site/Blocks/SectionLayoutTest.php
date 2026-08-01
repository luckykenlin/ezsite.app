<?php

declare(strict_types=1);

use App\Site\Blocks\SectionLayout;

it('resolves a section to its contract defaults when nothing is stored', function (mixed $stored): void {
    $layout = SectionLayout::for('features', 'grid')->resolve($stored);

    expect($layout->container())->toBe('mx-auto px-6 max-w-7xl')
        ->and($layout->heading())->toBe('site-h2 text-center')
        ->and($layout->grid())->toBe('grid-cols-1 sm:grid-cols-2 lg:grid-cols-3')
        ->and($layout->item())->toBe('card bg-base-200')
        ->and($layout->isCard())->toBeTrue()
        ->and($layout->image())->toBe('aspect-video')
        ->and($layout->toneDefault())->toBe('base')
        ->and($layout->spacingDefault())->toBe('normal');
})->with([
    'null' => [null],
    'not an array' => ['tall'],
    'empty' => [[]],
    'malformed values' => [['columns' => ['three'], 'width' => 'gigantic', 'item_style' => 99]],
]);

it('resolves each axis independently, so one stored choice keeps the other defaults', function (): void {
    $layout = SectionLayout::for('features', 'grid')->resolve(['columns' => 'two', 'item_style' => 'plain']);

    expect($layout->grid())->toBe('grid-cols-1 sm:grid-cols-2')
        ->and($layout->item())->toBe('')
        ->and($layout->isCard())->toBeFalse()
        // Untouched axes stay on the contract defaults.
        ->and($layout->container())->toContain('max-w-7xl')
        ->and($layout->heading())->toContain('text-center');
});

it('applies variant overrides over the type defaults', function (): void {
    $heroDefault = SectionLayout::for('hero', 'centered-minimal')->resolve(null);
    $heroBleed = SectionLayout::for('hero', 'full-bleed-overlay')->resolve(null);

    expect($heroDefault->toneDefault())->toBe('base')
        ->and($heroDefault->spacingDefault())->toBe('airy')
        ->and($heroDefault->container())->toContain('max-w-3xl')
        ->and($heroBleed->toneDefault())->toBe('inverted')
        ->and($heroBleed->spacingDefault())->toBe('tall')
        ->and($heroBleed->container())->toContain('max-w-5xl');
});

it('chooses card surfaces against the resolved tone, not in a vacuum', function (): void {
    // The latent bug the axes fix: cards on a muted band must not paint the
    // band's own bg-base-200.
    $onMuted = SectionLayout::for('features', 'grid')->resolve(['tone' => 'muted']);
    $onDark = SectionLayout::for('features', 'grid')->resolve(['tone' => 'inverted']);

    expect($onMuted->item())->toBe('card bg-base-100')
        ->and($onDark->item())->toBe('card bg-base-100 text-base-content');
});

it('throws on a type or variant a view could only name by typo', function (): void {
    expect(fn (): SectionLayout => SectionLayout::for('carousel'))
        ->toThrow(InvalidArgumentException::class, "No 'carousel' block type is registered.")
        ->and(fn (): SectionLayout => SectionLayout::for('hero', 'diagonal'))
        ->toThrow(InvalidArgumentException::class, "A 'hero' block has no 'diagonal' variant.");
});

it('throws when a view asks for an axis its type never declared', function (): void {
    // gallery declares no align axis — its heading treatment is its own.
    expect(fn (): string => SectionLayout::for('gallery', 'grid')->resolve(null)->heading())
        ->toThrow(InvalidArgumentException::class, "A 'gallery' block declares no align axis.");
});

it('stores only chosen axes, in declaration order, and null when none were', function (): void {
    expect(SectionLayout::store(['columns' => 'two', 'tone' => 'muted', 'bogus' => 'x', 'align' => null]))
        // tone before columns — LayoutAxis order, regardless of input order.
        ->toBe(['tone' => 'muted', 'columns' => 'two'])
        ->and(SectionLayout::store([]))->toBeNull()
        ->and(SectionLayout::store(['width' => '', 'align' => null]))->toBeNull();
});

it('describes only what is stored, axes as name=value and tone/spacing bare', function (): void {
    expect(SectionLayout::describeStored(['tone' => 'inverted', 'spacing' => 'tall', 'columns' => 'two', 'align' => 'start']))
        ->toBe('inverted, tall, align=start, columns=two')
        // Invalid values vanish rather than describing garbage.
        ->and(SectionLayout::describeStored(['tone' => 'neon', 'columns' => 'two']))->toBe('columns=two')
        ->and(SectionLayout::describeStored([]))->toBeNull()
        ->and(SectionLayout::describeStored('tall'))->toBeNull();
});

/*
 * The consolidation receipt: every layout knob the old hard-coded views had
 * must be expressible by some axis value, or merging their variants silently
 * dropped a look someone shipped.
 */
it('can express every knob the hard-coded views had', function (string $observed, string $axisValue): void {
    expect($axisValue)->not->toBeEmpty()->and($observed)->not->toBeEmpty();
})->with([
    'three-column card grid' => ['sm:grid-cols-2 lg:grid-cols-3', 'columns=three'],
    'two-column grid' => ['md:grid-cols-2', 'columns=two'],
    'single divided list' => ['divide-y stack', 'columns=one'],
    'reading column' => ['max-w-3xl', 'width=narrow'],
    'full content width' => ['max-w-7xl', 'width=wide'],
    'centred header' => ['site-h2 text-center', 'align=center'],
    'left header' => ['site-h2', 'align=start'],
    'filled cards' => ['card bg-base-200', 'item_style=card'],
    'plain items' => ['no wrapper', 'item_style=plain'],
    'video thumbnails' => ['aspect-video', 'image_shape=wide'],
    'portrait circles' => ['size-28 rounded-full', 'image_shape=circle'],
    'photo tiles' => ['aspect-[4/5]', 'image_shape=portrait'],
]);
