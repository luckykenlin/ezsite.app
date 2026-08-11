<?php

declare(strict_types=1);

namespace App\Design;

/**
 * The shape language between sections — the first of the "signature
 * treatment" tokens PLAN.md called for. Every `<x-site.section>` renders a
 * `.site-divider` element at its top edge; this token decides whether it
 * shows and what silhouette it cuts (see the divider rules in
 * resources/css/site.css). The divider paints in the section's own
 * background and overlaps the previous section's bottom padding, so it is
 * only visible where two adjacent tones differ — which is exactly when a
 * shaped seam reads as intentional.
 *
 * Values are geometry only: `--divider-clip` is a `clip-path` the enum
 * authors, so tenant data can never reach the property (same boundary as
 * every other token).
 */
enum SectionDivider: string
{
    case None = 'none';
    case Slant = 'slant';
    case Curve = 'curve';
    case Peak = 'peak';

    /**
     * @return array<string, string>
     */
    public function variables(): array
    {
        // All three are drawn SHALLOW, and that is the whole revision. Cutting
        // the full 3rem of the seam gave a hero a dome across its foot, a
        // section a chevron pointing down at the next one, and a page a wedge
        // — the three shapes every template site of about 2015 used, which is
        // exactly what a visitor reads them as now. Topping out at roughly
        // half the seam's height turns each one from an ornament into a fold:
        // still legible as a deliberate edge where two tones meet, no longer
        // the loudest thing between two sections.
        [$display, $clip] = match ($this) {
            self::None => ['none', 'none'],
            self::Slant => ['block', 'polygon(0% 100%, 100% 40%, 100% 100%)'],
            // A wide ellipse rather than a tall one: the radius runs past both
            // edges, so the visitor sees the flat middle of a big arc instead
            // of the top of a small one.
            self::Curve => ['block', 'ellipse(120% 55% at 50% 100%)'],
            self::Peak => ['block', 'polygon(0% 100%, 50% 35%, 100% 100%)'],
        };

        return [
            '--divider-display' => $display,
            '--divider-clip' => $clip,
        ];
    }
}
