<?php

declare(strict_types=1);

namespace App\Filament\Fabricator;

use App\Enums\BindType;
use App\Filament\Fabricator\PageBlocks\Block;
use App\Models\Business;
use App\Models\Location;
use Z3d0X\FilamentFabricator\Facades\FilamentFabricator;

/**
 * Aggregation layer over FilamentFabricator's name→class manager.
 *
 * Two jobs:
 *  1. {@see vocabulary()} — the enumerated set of block contracts. This is the
 *     "vocabulary" the AI layer is allowed to compose from, and the hard
 *     boundary that keeps blocks *selectable but never authorable* by tenants.
 *  2. {@see resolveComponent()} / {@see normalizeData()} — the defensive glue the
 *     overridden render view uses so an unknown type, invalid variant, or
 *     malformed `data` degrades gracefully (skip + log) instead of fataling on a
 *     live tenant site.
 *
 * Variant and bind are read from reserved keys *inside* `data` (see {@see Block}).
 */
final class BlockRegistry
{
    /**
     * The media-reference key conventions: a block schema stores the picker
     * id under the left key, and the render layer injects the resolved
     * public URL under the right key — the SAME prop the views always read,
     * so no view knows media ids exist. A resolved id wins over any stored
     * URL string; a dangling id injects nothing and the stored string (or
     * the view's own empty-image guard) takes over.
     */
    private const array MEDIA_KEYS = [
        'image_id' => 'image_url',
        'media_id' => 'url',
        'avatar_media_id' => 'avatar_url',
    ];

    /**
     * Every registered block's machine-readable contract, keyed by type.
     *
     * @return array<string, array{type: string, variants: list<string>, bind: string|null, icon: string|null, fields: list<string>}>
     */
    public static function vocabulary(): array
    {
        $vocabulary = [];

        foreach (FilamentFabricator::getPageBlocksRaw() as $class) {
            if (is_string($class) && is_subclass_of($class, Block::class)) {
                $vocabulary[$class::getName()] = $class::contract();
            }
        }

        return $vocabulary;
    }

    /**
     * Resolve the Blade component a stored block should render into, or null if
     * the block cannot be safely rendered: an unknown type, a type not backed by
     * an app {@see Block} (never authorable by tenants), or an explicitly invalid
     * variant. A *missing* variant falls back to the block's default; only a
     * non-empty, unrecognised variant is rejected.
     *
     * @param  array<string, mixed>  $block
     */
    public static function resolveComponent(array $block): ?string
    {
        $type = self::blockType($block);

        if ($type === null) {
            return null;
        }

        $class = FilamentFabricator::getPageBlockFromName($type);

        if (! is_string($class) || ! is_subclass_of($class, Block::class)) {
            return null;
        }

        $base = 'filament-fabricator.page-blocks.'.$type;

        if ($class::variants() === []) {
            return $base;
        }

        $variant = self::variant($block, $class);

        return $variant === null ? null : $base.'.'.$variant;
    }

    /**
     * The block's `data`, guaranteed to be an array and with the variant reserved
     * key backfilled to the block's default when absent. Used to build the
     * component's attributes after {@see resolveComponent()} has accepted it.
     *
     * @param  array<string, mixed>  $block
     * @return array<array-key, mixed>
     */
    public static function normalizeData(array $block): array
    {
        $data = is_array($block['data'] ?? null) ? $block['data'] : [];

        $type = self::blockType($block);
        $class = $type === null ? null : FilamentFabricator::getPageBlockFromName($type);

        if ($class !== null && is_subclass_of($class, Block::class) && $class::variants() !== []) {
            $current = $data[Block::VARIANT_KEY] ?? null;

            if (! is_string($current) || $current === '') {
                $data[Block::VARIANT_KEY] = $class::defaultVariant();
            }
        }

        return $data;
    }

    /**
     * Every media id a stored block references (top level + repeater items),
     * for batch preloading.
     *
     * @param  array<int, mixed>  $blocks
     * @return list<mixed>
     */
    public static function mediaIds(array $blocks): array
    {
        $ids = [];

        foreach ($blocks as $block) {
            $data = is_array($block) && is_array($block['data'] ?? null) ? $block['data'] : [];

            foreach (array_keys(self::MEDIA_KEYS) as $idKey) {
                $ids[] = $data[$idKey] ?? null;
            }

            foreach ($data as $value) {
                if (! is_array($value)) {
                    continue;
                }

                if (! array_is_list($value)) {
                    continue;
                }

                foreach ($value as $item) {
                    if (is_array($item)) {
                        foreach (array_keys(self::MEDIA_KEYS) as $idKey) {
                            $ids[] = $item[$idKey] ?? null;
                        }
                    }
                }
            }
        }

        return array_values(array_filter($ids, static fn (mixed $id): bool => $id !== null));
    }

    /**
     * Translate media-reference keys into the URL props the views consume
     * (see MEDIA_KEYS), one repeater level deep.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function resolveMediaUrls(array $data): array
    {
        $data = self::injectMediaUrls($data);

        foreach ($data as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            if (! array_is_list($value)) {
                continue;
            }

            $data[$key] = array_map(
                static fn (mixed $item): mixed => is_array($item) ? self::injectMediaUrls($item) : $item,
                $value,
            );
        }

        return $data;
    }

    /**
     * The bound model attributes a block's view should receive: `[]` when the
     * block declares no bind, the `business` (and, for Location binds, the
     * `location`) props when resolution succeeds, or null when the bind cannot
     * be resolved — no Business row, or a Location bind with zero locations.
     * The render loop skips null the same way it skips unresolved components.
     *
     * @param  array<string, mixed>  $block
     * @return array{business?: Business, location?: Location}|null
     */
    public static function bindAttributes(array $block): ?array
    {
        $type = self::blockType($block);
        $class = $type === null ? null : FilamentFabricator::getPageBlockFromName($type);

        if (! is_string($class) || ! is_subclass_of($class, Block::class)) {
            return [];
        }

        $bindType = $class::bindType();

        if ($bindType === null) {
            return [];
        }

        $resolver = resolve(BindResolver::class);
        $business = $resolver->business();

        if ($business === null) {
            return null;
        }

        if ($bindType === BindType::Business) {
            return ['business' => $business];
        }

        $location = $resolver->location(self::boundLocationId($block));

        return $location === null
            ? null
            : ['business' => $business, 'location' => $location];
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private static function injectMediaUrls(array $data): array
    {
        foreach (self::MEDIA_KEYS as $idKey => $urlKey) {
            $url = resolve(MediaResolver::class)->url($data[$idKey] ?? null);

            if ($url !== null) {
                $data[$urlKey] = $url;
            }
        }

        return $data;
    }

    /**
     * The `data.bind.location_id` a block stores, defensively: anything that is
     * not an integer (or an all-digit string, as Filament selects dehydrate) is
     * treated as "unset" and resolves to the primary location.
     *
     * @param  array<string, mixed>  $block
     */
    private static function boundLocationId(array $block): ?int
    {
        $data = is_array($block['data'] ?? null) ? $block['data'] : [];
        $bind = $data[Block::BIND_KEY] ?? null;
        $id = is_array($bind) ? ($bind['location_id'] ?? null) : null;

        if (is_int($id)) {
            return $id;
        }

        return is_string($id) && ctype_digit($id) ? (int) $id : null;
    }

    /**
     * The block's `type`, or null when structurally malformed (missing / non-string).
     *
     * @param  array<string, mixed>  $block
     */
    private static function blockType(array $block): ?string
    {
        $type = $block['type'] ?? null;

        return is_string($type) && $type !== '' ? $type : null;
    }

    /**
     * The variant to render: the block's default when unset, the stored value
     * when valid, or null when a non-empty stored value is unrecognised.
     *
     * @param  array<string, mixed>  $block
     * @param  class-string<Block>  $class
     */
    private static function variant(array $block, string $class): ?string
    {
        $data = is_array($block['data'] ?? null) ? $block['data'] : [];
        $variant = $data[Block::VARIANT_KEY] ?? null;

        if (! is_string($variant) || $variant === '') {
            return $class::defaultVariant();
        }

        return array_key_exists($variant, $class::variants()) ? $variant : null;
    }
}
