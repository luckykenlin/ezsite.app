<?php

declare(strict_types=1);

namespace App\Site\Blocks;

/**
 * What a block is FOR, from the operator's point of view — the grouping the
 * block library browses by.
 *
 * An intent taxonomy rather than an alphabetical list, because that is how a
 * small-business owner shops for a section: "I need something that builds
 * trust", not "I need a testimonials component". Case order is display order —
 * roughly the top-to-bottom order of a typical page.
 *
 * Chrome (header/footer) declares no intent: it is never offered in the
 * library at all. Every page-level block must declare one — enforced by
 * "gives every page block a library intent, and chrome none" in
 * tests/Unit/Filament/Fabricator/BlockRegistryTest.php — since a block without
 * a group would silently vanish from the library.
 */
enum BlockIntent: string
{
    case Introduce = 'introduce';
    case Showcase = 'showcase';
    case Trust = 'trust';
    case Convert = 'convert';

    /**
     * The group heading in the block library.
     */
    public function label(): string
    {
        return match ($this) {
            self::Introduce => __('Tell your story'),
            self::Showcase => __('Show what you offer'),
            self::Trust => __('Build trust'),
            self::Convert => __('Turn visitors into customers'),
        };
    }
}
