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

    /**
     * The full deterministic `--type-*` set. Weights stay on the 500–800
     * stops the self-hosted font files actually ship (see the bunny() calls
     * in vite.config.js) — a browser quietly snaps a missing weight to the
     * nearest loaded one, which softens a style instead of breaking it, but
     * the declared stops should still be real for the common pairs.
     *
     * @return array<string, string>
     */
    public function variables(): array
    {
        [$scale, $displayWeight, $displayTracking, $headingWeight, $headingTracking, $headingCase, $subheadingWeight] = match ($this) {
            self::Classic => ['1', '700', '-0.025em', '700', '-0.025em', 'none', '600'],
            self::Refined => ['1.05', '500', '-0.015em', '600', '-0.01em', 'none', '500'],
            self::Impact => ['1.1', '800', '-0.04em', '800', '-0.02em', 'uppercase', '700'],
            self::Editorial => ['1.08', '700', '-0.03em', '600', '-0.02em', 'none', '600'],
            self::Friendly => ['1', '700', '-0.015em', '700', '-0.01em', 'none', '600'],
            self::Quiet => ['0.94', '600', '-0.02em', '600', '0.06em', 'uppercase', '500'],
        };

        [$eyebrowSize, $eyebrowWeight, $eyebrowCase, $eyebrowTracking, $introSize] = match ($this) {
            self::Classic => ['0.875rem', '600', 'uppercase', '0.1em', '1.125rem'],
            self::Refined => ['0.8125rem', '500', 'uppercase', '0.16em', '1.125rem'],
            self::Impact => ['0.875rem', '700', 'uppercase', '0.12em', '1.125rem'],
            self::Editorial => ['0.9375rem', '500', 'lowercase', '0.08em', '1.25rem'],
            self::Friendly => ['0.875rem', '700', 'capitalize', '0.05em', '1.125rem'],
            self::Quiet => ['0.8125rem', '500', 'uppercase', '0.14em', '1.0625rem'],
        };

        return [
            '--type-scale' => $scale,
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
