<?php

declare(strict_types=1);

namespace App\Design;

/**
 * Enumerated spacing density. Overriding Tailwind's root `--spacing` unit
 * (±~10%) scales every spacing utility in the block views at once — zero
 * view edits; the DaisyUI `--size-*` variables keep controls in step.
 */
enum SpacingDensity: string
{
    case Compact = 'compact';
    case Normal = 'normal';
    case Spacious = 'spacious';

    /**
     * @return array<string, string>
     */
    public function variables(): array
    {
        [$spacing, $size] = match ($this) {
            self::Compact => ['0.225rem', '0.21875rem'],
            self::Normal => ['0.25rem', '0.25rem'],
            self::Spacious => ['0.28125rem', '0.28125rem'],
        };

        return [
            '--spacing' => $spacing,
            '--size-field' => $size,
            '--size-selector' => $size,
        ];
    }
}
