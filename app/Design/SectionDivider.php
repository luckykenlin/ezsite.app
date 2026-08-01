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
        [$display, $clip] = match ($this) {
            self::None => ['none', 'none'],
            self::Slant => ['block', 'polygon(0% 100%, 100% 0%, 100% 100%)'],
            self::Curve => ['block', 'ellipse(75% 100% at 50% 100%)'],
            self::Peak => ['block', 'polygon(0% 100%, 50% 0%, 100% 100%)'],
        };

        return [
            '--divider-display' => $display,
            '--divider-clip' => $clip,
        ];
    }

    public function description(): string
    {
        return match ($this) {
            self::None => 'straight seams between sections — the default',
            self::Slant => 'a diagonal cut where one section meets the next',
            self::Curve => 'a soft dome easing each section into the last',
            self::Peak => 'a centred point rising into the section above',
        };
    }
}
