<?php

declare(strict_types=1);

namespace App\Design;

/**
 * Enumerated corner-radius steps, mapped onto DaisyUI's radius variables.
 * `Md` mirrors the DaisyUI light-theme defaults.
 */
enum RadiusScale: string
{
    case None = 'none';
    case Sm = 'sm';
    case Md = 'md';
    case Lg = 'lg';
    case Full = 'full';

    /**
     * @return array<string, string>
     */
    public function variables(): array
    {
        [$selector, $field, $box] = match ($this) {
            self::None => ['0', '0', '0'],
            self::Sm => ['0.25rem', '0.25rem', '0.25rem'],
            self::Md => ['0.5rem', '0.25rem', '0.5rem'],
            self::Lg => ['1rem', '0.5rem', '1rem'],
            self::Full => ['2rem', '2rem', '2rem'],
        };

        return [
            '--radius-selector' => $selector,
            '--radius-field' => $field,
            '--radius-box' => $box,
        ];
    }
}
