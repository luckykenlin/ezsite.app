<?php

declare(strict_types=1);

namespace App\Site\Blocks;

/**
 * The reserved keys inside a stored block's `data`.
 *
 * Filament Fabricator persists only `{type, data}`, so this app's two extras —
 * which layout variant to render, and which record a block binds to — live as
 * reserved keys INSIDE `data` rather than as columns.
 *
 * They describe the persisted JSON, not a form, which is why they live here and
 * not on the Filament block class they used to: the AI layer, the page actions
 * and the render glue all read them, and only one of those three has any business
 * knowing Filament exists.
 */
final class BlockShape
{
    public const string VARIANT_KEY = 'variant';

    public const string BIND_KEY = 'bind';

    /**
     * Both reserved keys, for stripping them out of author-supplied data.
     *
     * @return list<string>
     */
    public static function reservedKeys(): array
    {
        return [self::VARIANT_KEY, self::BIND_KEY];
    }
}
