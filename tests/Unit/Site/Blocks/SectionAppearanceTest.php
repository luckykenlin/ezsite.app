<?php

declare(strict_types=1);

use App\Site\Blocks\SectionAppearance;
use App\Site\Blocks\SectionSpacing;
use App\Site\Blocks\SectionTone;

/**
 * The per-block appearance dimension: two enumerated scales plus the resolver
 * that merges a block's stored choice over the defaults its view declares.
 *
 * The asymmetry in `resolve()` is the point of most of these: our own defaults
 * fail LOUD (a typo in a block view is a bug), tenant data fails SAFE (a bad
 * stored value must never break a live page).
 */
it('falls back to the view-declared defaults when nothing is stored', function (mixed $stored): void {
    $appearance = SectionAppearance::resolve($stored, 'muted', 'airy');

    expect($appearance->tone)->toBe(SectionTone::Muted)
        ->and($appearance->spacing)->toBe(SectionSpacing::Airy);
})->with([
    'null' => [null],
    'empty array' => [[]],
    'not an array' => ['inverted'],
    'unrecognised values' => [['tone' => 'neon', 'spacing' => 'enormous']],
    'non-string values' => [['tone' => 3, 'spacing' => ['tall']]],
]);

it('resolves each dimension independently, so a stored tone keeps the view spacing', function (): void {
    $appearance = SectionAppearance::resolve(['tone' => 'inverted'], 'base', 'tight');

    expect($appearance->tone)->toBe(SectionTone::Inverted)
        ->and($appearance->spacing)->toBe(SectionSpacing::Tight);
});

it('lets a stored appearance override both view defaults', function (): void {
    $appearance = SectionAppearance::resolve(['tone' => 'accent', 'spacing' => 'tall'], 'base', 'normal');

    // Accent is a component class, not a utility pair: its surface is the
    // AccentStyle token's to paint (a var-driven gradient needs site.css).
    expect($appearance->toneClasses())->toBe('site-tone-accent')
        ->and($appearance->spacingClasses())->toBe('py-32 md:py-48');
});

it('throws on a default a block view could only get wrong by typo', function (): void {
    // Deliberately loud: unlike stored data, this value is ours, and a silent
    // fallback would ship a section with the wrong background forever.
    SectionAppearance::resolve(null, 'bass', 'normal');
})->throws(ValueError::class);

it('stores only the dimensions that were chosen, and nothing at all when neither was', function (): void {
    expect(SectionAppearance::store(SectionTone::Muted, null))->toBe(['tone' => 'muted'])
        ->and(SectionAppearance::store(null, SectionSpacing::Flush))->toBe(['spacing' => 'flush'])
        ->and(SectionAppearance::store(SectionTone::Base, SectionSpacing::Normal))
        ->toBe(['tone' => 'base', 'spacing' => 'normal'])
        // Null, not [] — an empty array would survive as a key the editor's
        // commit then strips, manufacturing a revision out of nothing.
        ->and(SectionAppearance::store(null, null))->toBeNull();
});

it('describes only what is stored, so an untouched section reads as untouched', function (mixed $stored, ?string $expected): void {
    expect(SectionAppearance::describeStored($stored))->toBe($expected);
})->with([
    'nothing stored' => [null, null],
    'garbage' => [['tone' => 'neon'], null],
    'tone only' => [['tone' => 'inverted'], 'inverted'],
    'spacing only' => [['spacing' => 'tight'], 'tight'],
    'both' => [['tone' => 'muted', 'spacing' => 'airy'], 'muted, airy'],
]);

it('pairs a foreground with every background so text stays legible on it', function (SectionTone $tone): void {
    $classes = $tone->classes();

    if ($tone === SectionTone::Plain) {
        expect($classes)->toBeEmpty();

        return;
    }

    if ($tone === SectionTone::Accent) {
        // Accent's background/foreground pair lives in site.css
        // (.site-tone-accent), where the AccentStyle token can paint a
        // var-driven gradient no Tailwind utility could carry.
        expect($classes)->toBe('site-tone-accent');

        return;
    }

    expect($classes)->toContain('bg-')->toContain('text-');
})->with(SectionTone::cases());

it('scales vertical padding responsively at every step', function (SectionSpacing $spacing): void {
    expect($spacing->classes())->toMatch('/^py-\d+ md:py-\d+$/');
})->with(SectionSpacing::cases());

it('keeps the spacing scale able to express every padding a block view had', function (): void {
    // The five steps exist because the views between them hard-coded exactly
    // these five pairs. If a step is ever retuned, the view that relied on it
    // changes silently — so pin the whole scale.
    $classes = array_map(
        static fn (SectionSpacing $spacing): string => $spacing->classes(),
        SectionSpacing::cases(),
    );

    expect($classes)->toBe([
        'py-8 md:py-12',
        'py-16 md:py-20',
        'py-20 md:py-28',
        'py-24 md:py-32',
        'py-32 md:py-48',
    ]);
});
