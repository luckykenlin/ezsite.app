<?php

declare(strict_types=1);

use App\Design\FontPair;
use App\Design\StylePreset;
use App\Design\TypeStyle;

/**
 * The families bundled by the vite fonts plugin, as `family => weights`.
 *
 * Parsed from vite.config.js rather than mirrored in PHP: a copy here would be
 * a second source of truth, and the failure this guards against is precisely
 * the two lists drifting apart.
 */
function bundledFonts(): array
{
    preg_match_all(
        "/bunny\('([^']+)',\s*\{\s*weights:\s*\[([\d,\s]+)\]/",
        (string) file_get_contents(dirname(__DIR__, 3).'/vite.config.js'),
        $matches,
        PREG_SET_ORDER,
    );

    $bundled = [];

    foreach ($matches as [, $family, $weights]) {
        $bundled[$family] = array_map(intval(...), preg_split('/\s*,\s*/', mb_trim($weights, " \t\n\r,")) ?: []);
    }

    return $bundled;
}

it('resolves both families and a matching stack for every pair', function (FontPair $pair): void {
    expect($pair->headingFamily())->not->toBeEmpty()
        ->and($pair->bodyFamily())->not->toBeEmpty()
        // The stacks are what reach --font-heading / --font-sans, so the
        // family must be quoted first and a generic must close the list.
        ->and($pair->headingStack())->toStartWith("'".$pair->headingFamily()."', ")
        ->and($pair->bodyStack())->toStartWith("'".$pair->bodyFamily()."', ")
        ->and($pair->headingStack())->toEndWith('serif')
        ->and($pair->bodyStack())->toEndWith('sans-serif');
})->with(FontPair::cases());

it('slugs its families into the aliases the vite manifest keys, without repeats', function (FontPair $pair): void {
    $aliases = $pair->viteAliases();

    expect($aliases)->each->toMatch('/^[a-z0-9-]+$/')
        ->and($aliases)->toBe(array_values(array_unique($aliases)))
        // A pair that sets both roles in one family preloads it once, not twice.
        ->and($aliases)->toHaveCount($pair->headingFamily() === $pair->bodyFamily() ? 1 : 2);
})->with(FontPair::cases());

/*
 * The claim the FontPair docblock has always made and nothing enforced: every
 * family a pair can pick is self-hosted at build time. An unbundled family is
 * invisible in every PHP test — the stack still names it, `Vite::fonts()` just
 * emits no preload — and the site silently falls back to Georgia or system-ui
 * for whichever tenant chose it.
 */
it('bundles every family a pair can resolve to', function (): void {
    $bundled = bundledFonts();

    expect($bundled)->not->toBeEmpty();

    $missing = [];

    foreach (FontPair::cases() as $pair) {
        foreach ([$pair->headingFamily(), $pair->bodyFamily()] as $family) {
            if (! array_key_exists($family, $bundled)) {
                $missing[] = $pair->value.' needs '.$family;
            }
        }
    }

    expect($missing)->toBeEmpty();
});

/*
 * And the reverse, because an orphan is a download every tenant pays for: a
 * bundled family nothing can select is dead weight in the fonts manifest.
 */
it('bundles nothing no pair can select', function (): void {
    $selectable = [];

    foreach (FontPair::cases() as $pair) {
        $selectable[$pair->headingFamily()] = true;
        $selectable[$pair->bodyFamily()] = true;
    }

    expect(array_diff(array_keys(bundledFonts()), array_keys($selectable)))->toBeEmpty();
});

/*
 * The pairing rule that makes a sub-500 display weight safe.
 *
 * TypeStyle::Serene sets its display at 300 and every other style sits at 500
 * or above, because a browser snaps a missing weight to the nearest bundled one
 * — a forgiving failure for most type, and a total one for a high-contrast
 * serif at 6rem. So a light-display style may only ship inside a preset whose heading
 * face is drawn for it AND actually bundles the weight.
 */
it('only pairs a light display weight with a face drawn and bundled for it', function (StylePreset $preset): void {
    $tokens = $preset->tokens();
    $weight = (int) $tokens->typeStyle->variables()['--type-display-weight'];
    $bundled = bundledFonts()[$tokens->fontPair->headingFamily()] ?? [];

    // Stated as an implication rather than an early return, so a preset that
    // sits at 500 or above still asserts something: that it is allowed to.
    expect($weight >= 500 || $tokens->fontPair->supportsLightDisplay())->toBeTrue()
        ->and($weight >= 500 || in_array($weight, $bundled, true))->toBeTrue();
})->with(StylePreset::cases());

it('draws exactly one face finely enough to whisper', function (): void {
    // Kept explicit: `supportsLightDisplay()` is a judgement about a typeface,
    // so the one face that carries it should be named where a reader can argue
    // with the choice.
    expect(array_values(array_filter(FontPair::cases(), fn (FontPair $pair): bool => $pair->supportsLightDisplay())))
        ->toBe([FontPair::WarmEditorial])
        ->and(TypeStyle::Serene->variables()['--type-display-weight'])->toBe('300');
});
