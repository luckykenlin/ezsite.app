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

    public const string APPEARANCE_KEY = 'appearance';

    /**
     * The two keys nested under {@see APPEARANCE_KEY}. Nested rather than two
     * more top-level keys so the whole dimension is one thing to reserve, one
     * thing to strip, and one thing the editor's `withoutNulls` commit drops
     * when the operator clears both selects.
     */
    public const string TONE_KEY = 'tone';

    public const string SPACING_KEY = 'spacing';

    /**
     * The server-side slot for a block's stock-photo search query, in transit
     * between draft generation and {@see \App\Actions\Pages\PopulateDraftImages},
     * which consumes and removes it. The model proposes the query under the
     * UNPREFIXED name `image_query`; {@see \App\Ai\SiteDraftValidator} harvests
     * that before sanitization and re-attaches it here. Reserved (underscored,
     * stripped by the sanitizer) so neither AI write path can author it
     * directly — the validator is its one door, like every other key on this
     * list.
     */
    public const string IMAGE_QUERY_KEY = '_image_query';

    /**
     * Every reserved key, for stripping them out of author-supplied data.
     *
     * Only the TOP-LEVEL names belong here: this list is what
     * {@see \App\Ai\BlockDataSanitizer} refuses to let the model write, and
     * `appearance` covers its nested pair. Writing an appearance goes through
     * {@see \App\Ai\Tools\SetBlockAppearance}, exactly as a variant goes through
     * `SetBlockVariant` — one validating door per server-owned key.
     *
     * @return list<string>
     */
    public static function reservedKeys(): array
    {
        return [self::VARIANT_KEY, self::BIND_KEY, self::APPEARANCE_KEY, self::IMAGE_QUERY_KEY];
    }
}
