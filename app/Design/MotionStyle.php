<?php

declare(strict_types=1);

namespace App\Design;

/**
 * Whether the site's sections arrive as the visitor scrolls, and by how much.
 *
 * A token rather than a hard-coded behaviour for the reason
 * {@see \App\Site\Blocks\SectionSpacing} spells out: `resources/js/site/reveal.ts`
 * marks every section shell on every tenant site, so shipping motion
 * unconditionally would have silently restyled every live site in the
 * installation. {@see self::Still} emits values that make the shared CSS a
 * no-op — opacity 1, no shift, no transition — so a site that has never chosen
 * a motion style renders byte-identically to the pre-token markup, the same
 * zero-regression strategy {@see TypeStyle} uses.
 *
 * Amplitude lives here rather than in the script deliberately: the JS only ever
 * decides WHEN an element has arrived (it adds `.is-visible`), never what
 * arriving looks like. That keeps the whole design decision in one enum a
 * reader can diff, and it is why there is no "motion off" branch in the script
 * — off is a duration of zero.
 *
 * No `prefers-reduced-motion` case, on purpose: honouring that setting is not a
 * look an operator may choose between, so it belongs in the stylesheet, which
 * overrides all of these unconditionally.
 */
enum MotionStyle: string
{
    case Still = 'still';
    case Reveal = 'reveal';

    /**
     * @return array<string, string>
     */
    public function variables(): array
    {
        // The shift is deliberately small. A section that travels further than
        // its own first line of text reads as a slide show rather than as a
        // page settling, and on a long page the effect compounds.
        [$opacity, $shift, $duration] = match ($this) {
            self::Still => ['1', '0px', '0ms'],
            self::Reveal => ['0', '1.75rem', '700ms'],
        };

        return [
            '--reveal-opacity' => $opacity,
            '--reveal-shift' => $shift,
            '--reveal-duration' => $duration,
        ];
    }
}
