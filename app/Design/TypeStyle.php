<?php

declare(strict_types=1);

namespace App\Design;

/**
 * The typography SYSTEM token: weight, tracking, case, size scale and eyebrow
 * treatment as one coherent package. This is the axis PLAN.md called out as
 * the missing typography differentiator — {@see FontPair} only swaps the
 * FAMILY, so before this token every site set every heading in
 * `700 / -0.025em / sentence case` regardless of look.
 *
 * Packages rather than free axes on purpose (the same argument as
 * {@see StylePreset}): weight, tracking and case interact — 800 wants tighter
 * tracking, uppercase wants a smaller size — and letting the operator or the
 * AI dial them independently is how a site starts to look wrong.
 *
 * `variables()` fills the `--type-*` contract consumed by the `.site-h*` /
 * `.site-eyebrow` / `.site-intro` classes in resources/css/site.css. Classic
 * emits exactly those classes' fallback values, so a site that has never
 * chosen a type style renders byte-identically to the pre-token markup —
 * the same zero-regression strategy as {@see \App\Site\Blocks\SectionAppearance}.
 */
enum TypeStyle: string
{
    case Classic = 'classic';
    case Refined = 'refined';
    case Impact = 'impact';
    case Editorial = 'editorial';
    case Friendly = 'friendly';
    case Quiet = 'quiet';
    case Serene = 'serene';

    /**
     * The full deterministic `--type-*` set.
     *
     * Weights sit on the stops the self-hosted font files actually ship (see the
     * bunny() calls in vite.config.js) — a browser quietly snaps a missing weight
     * to the nearest loaded one, which softens a style instead of breaking it,
     * but the declared stops should still be real for the common pairs. That
     * forgiveness runs out below 500, which is why {@see self::Serene} is the one
     * case allowed there and why its pairing is guarded (see
     * {@see FontPair::supportsLightDisplay()} and FontPairTest).
     *
     * `--type-display-scale` multiplies ONLY the display sizes, on top of
     * `--type-scale`. It exists because the display-to-heading RATIO is a style
     * decision of its own: a hero headline can be twice the size of a section
     * title or four times it, and before this axis the only way to grow one was
     * to grow the whole page's type with it — which is how "make the headline
     * enormous" turned into 20px body copy. Kept off `.site-stat`, whose
     * three-or-four-across row overflows long before a headline does.
     *
     * The ceiling on it is set by real copy, not by taste. A magazine can set a
     * headline at 8rem because an editor wrote it to two words; a tenant's
     * headline is whatever their tagline says, and at 1.4 a nine-word one filled
     * a small laptop window on its own. So Serene sits at 1.25 — plainly larger
     * than the rest of the page, and still survives a sentence.
     *
     * @return array<string, string>
     */
    public function variables(): array
    {
        [$scale, $displayScale, $displayWeight, $displayTracking, $headingWeight, $headingTracking, $headingCase, $subheadingWeight] = match ($this) {
            self::Classic => ['1', '1', '700', '-0.025em', '700', '-0.025em', 'none', '600'],
            self::Refined => ['1.05', '1', '500', '-0.015em', '600', '-0.01em', 'none', '500'],
            self::Impact => ['1.1', '1.05', '800', '-0.04em', '800', '-0.02em', 'uppercase', '700'],
            self::Editorial => ['1.08', '1', '700', '-0.03em', '600', '-0.02em', 'none', '600'],
            self::Friendly => ['1', '1', '700', '-0.015em', '700', '-0.01em', 'none', '600'],
            self::Quiet => ['0.94', '0.95', '600', '-0.02em', '600', '0.06em', 'uppercase', '500'],
            // The one light-display style: a hairline serif set very large and
            // tracked at nothing, over body copy that stays an ordinary size.
            // Negative tracking is what a heavy face needs to stop looking
            // gappy; a 300-weight garamond at 6rem needs the opposite, so this
            // is the only case that opens its display tracking up rather than
            // pulling it in.
            self::Serene => ['1.05', '1.25', '300', '0.005em', '300', '0', 'none', '400'],
        };

        [$eyebrowSize, $eyebrowWeight, $eyebrowCase, $eyebrowTracking, $introSize] = match ($this) {
            self::Classic => ['0.875rem', '600', 'uppercase', '0.1em', '1.125rem'],
            self::Refined => ['0.8125rem', '500', 'uppercase', '0.16em', '1.125rem'],
            self::Impact => ['0.875rem', '700', 'uppercase', '0.12em', '1.125rem'],
            self::Editorial => ['0.9375rem', '500', 'lowercase', '0.08em', '1.25rem'],
            self::Friendly => ['0.875rem', '700', 'capitalize', '0.05em', '1.125rem'],
            self::Quiet => ['0.8125rem', '500', 'uppercase', '0.14em', '1.0625rem'],
            // The widest tracking in the set, on the smallest eyebrow: at
            // 0.3em the label stops reading as a word and starts reading as a
            // rule with letters on it, which is the whole device.
            self::Serene => ['0.75rem', '500', 'uppercase', '0.3em', '1.125rem'],
        };

        return [
            '--type-scale' => $scale,
            '--type-display-scale' => $displayScale,
            '--type-display-weight' => $displayWeight,
            '--type-display-tracking' => $displayTracking,
            '--type-heading-weight' => $headingWeight,
            '--type-heading-tracking' => $headingTracking,
            '--type-heading-case' => $headingCase,
            '--type-subheading-weight' => $subheadingWeight,
            '--type-eyebrow-size' => $eyebrowSize,
            '--type-eyebrow-weight' => $eyebrowWeight,
            '--type-eyebrow-case' => $eyebrowCase,
            '--type-eyebrow-tracking' => $eyebrowTracking,
            '--type-intro-size' => $introSize,
        ];
    }
}
