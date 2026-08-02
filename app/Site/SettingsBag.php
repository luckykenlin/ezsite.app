<?php

declare(strict_types=1);

namespace App\Site;

/**
 * Typed, fail-safe reads over one section-keyed JSON settings blob.
 *
 * The `site_settings` columns are tenant-authored JSON whose values reach
 * class attributes, data attributes and hrefs, so nothing here trusts a
 * shape: a missing section, a string where a bool should be, an array where
 * a scalar should be — all read as "absent". Extracted from
 * {@see SiteCapture}, which was carrying this kit alongside its actual job;
 * the next settings section reads through it instead of copying it.
 *
 * `int()` tolerates numeric strings because Filament stores a TextInput's
 * numeric value as a string.
 */
final readonly class SettingsBag
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(private array $data)
    {
        //
    }

    public function string(string $section, string $key): string
    {
        $value = $this->value($section, $key);

        return is_string($value) ? mb_trim($value) : '';
    }

    public function bool(string $section, string $key): bool
    {
        return $this->value($section, $key) === true;
    }

    public function int(string $section, string $key): ?int
    {
        $value = $this->value($section, $key);

        if (is_int($value)) {
            return $value;
        }

        return is_string($value) && ctype_digit($value) ? (int) $value : null;
    }

    public function value(string $section, string $key): mixed
    {
        $group = $this->data[$section] ?? null;

        return is_array($group) ? ($group[$key] ?? null) : null;
    }
}
