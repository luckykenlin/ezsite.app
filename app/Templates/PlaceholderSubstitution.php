<?php

declare(strict_types=1);

namespace App\Templates;

/**
 * The one substitution primitive shared by everything that turns `{token}`
 * copy into a real business's copy: a single recursive pass of literal
 * `strtr` over every string in a block tree.
 *
 * Extracted from {@see \App\Actions\Templates\FillTemplatePlaceholders} when
 * page presets became its second consumer — a preset personalizes one page
 * the same way a template personalizes a whole site, and duplicating the walk
 * would let the two drift. The FALLBACK policy (demo profile, template
 * fields) deliberately stays with the callers: what an unanswered token means
 * differs by surface, the walk does not.
 *
 * `strtr` rather than a loop of `str_replace` on purpose: it is single-pass,
 * so a value that itself contains braces (someone types `{city}` into the
 * city field) is never re-substituted.
 */
final readonly class PlaceholderSubstitution
{
    /**
     * @param  array<array-key, mixed>  $node
     * @param  array<string, string>  $values  `{token} => replacement`, already resolved
     * @return array<array-key, mixed>
     */
    public static function fill(array $node, array $values): array
    {
        foreach ($node as $key => $value) {
            if (is_string($value)) {
                $node[$key] = strtr($value, $values);
            } elseif (is_array($value)) {
                $node[$key] = self::fill($value, $values);
            }
        }

        return $node;
    }
}
