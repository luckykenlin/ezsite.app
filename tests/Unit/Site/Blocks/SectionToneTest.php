<?php

declare(strict_types=1);

use App\Site\Blocks\SectionTone;

/*
 * The band's own contract. `itemSurface()` has its tests next door with the item
 * style that composes it; this file covers the tone's other two class strings.
 */

it('always paints a background and foreground together', function (SectionTone $tone): void {
    // Setting one without the other is how a section ends up with dark text on
    // a dark band. `plain` is the documented exception: it paints neither.
    $classes = $tone->classes();

    if ($tone === SectionTone::Plain) {
        expect($classes)->toBeEmpty();

        return;
    }

    expect($classes)->not->toBeEmpty()
        // The accent surface is a single component class, because only CSS can
        // consume the var-driven gradient the AccentStyle token may put there.
        ->and($tone === SectionTone::Accent || str_contains($classes, 'text-'))->toBeTrue();
})->with(SectionTone::cases());

/*
 * The bug this method exists for, stated as the invariant that catches it: a
 * primary button on a band that IS the primary colour is a rectangle of
 * background. Every signup block in the library shipped that way, because
 * `Signup`'s own tone default is `accent`.
 */
it('never fills a call to action with the colour of the band behind it', function (SectionTone $tone): void {
    $button = $tone->buttonClasses();

    expect($button)->toStartWith('site-btn ');

    if ($tone !== SectionTone::Accent) {
        // Every other band leaves the brand colour visible, so the loudest
        // button available is the right one. `inverted` is included on purpose —
        // see the asymmetry argument on buttonClasses().
        expect($button)->toBe('site-btn site-btn-primary');

        return;
    }

    // Filled from the band's own CONTENT colour, which is the one slot
    // ColorPaletteTest guarantees reads against it — in every palette,
    // including the two monochrome ones where `neutral` would not have. The
    // colour itself lives on the class in site.css; what this asserts is that
    // the accent band reaches for a DIFFERENT fill than every other band.
    expect($button)->toBe('site-btn site-btn-on-accent')
        ->and($button)->not->toContain('site-btn-primary');
})->with(SectionTone::cases());

it('labels and describes every tone for the panel and the model', function (SectionTone $tone): void {
    expect($tone->label())->not->toBeEmpty()
        ->and($tone->description())->not->toBeEmpty();
})->with(SectionTone::cases());
