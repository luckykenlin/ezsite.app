<?php

declare(strict_types=1);

namespace App\Filament\Fabricator;

use App\Enums\BindType;
use App\Filament\Fabricator\PageBlocks\Block;
use App\Models\Business;
use App\Models\Location;
use App\Site\BindResolver;
use App\Site\Blocks\BlockShape;
use App\Site\Blocks\BlockType;
use App\Site\Blocks\BlockVocabulary;
use App\Site\MediaResolver;
use App\Site\UrlScheme;
use Illuminate\Support\Facades\Log;
use Z3d0X\FilamentFabricator\Facades\FilamentFabricator;

/**
 * The render-side glue between a stored `{type, data}` entry and the Blade
 * component that draws it.
 *
 * Its job is defensiveness: an unknown type, an invalid variant, a malformed
 * `data`, a dangling media id or an unresolvable bind must degrade gracefully
 * (skip + log) rather than fatal on a live tenant site. Consumed almost entirely
 * by the overridden `page-blocks` view.
 *
 * The one thing here that is NOT render glue is {@see contracts()}, which
 * enumerates the registered block classes. That enumeration is a domain concept —
 * see {@see BlockVocabulary}, which is what everything outside this namespace
 * reads; this class only supplies the raw material, because it is on the side of
 * the fence that can see the Filament block classes.
 *
 * Variant and bind are read from reserved keys *inside* `data`
 * (see {@see BlockShape}).
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
     * Every registered block's contract, keyed by type — the raw material for
     * {@see BlockVocabulary}, which is what the rest of the app actually reads.
     *
     * Lives here because this is the side that can enumerate the Filament block
     * classes; it is assembled into the domain-layer vocabulary once per request
     * by {@see \App\Providers\AppServiceProvider}.
     *
     * @return array<string, BlockType>
     */
    public static function contracts(): array
    {
        $contracts = [];

        foreach (FilamentFabricator::getPageBlocksRaw() as $class) {
            if (is_string($class) && is_subclass_of($class, Block::class)) {
                $contracts[$class::getName()] = $class::contract();
            }
        }

        return $contracts;
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
        $blockType = self::blockTypeFor($block);

        if (! $blockType instanceof BlockType) {
            return null;
        }

        $base = 'filament-fabricator.page-blocks.'.$blockType->type;

        if ($blockType->variants === []) {
            return $base;
        }

        $data = is_array($block['data'] ?? null) ? $block['data'] : [];
        $variant = $blockType->resolveVariant($data[BlockShape::VARIANT_KEY] ?? null);

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
        $blockType = self::blockTypeFor($block);

        if ($blockType instanceof BlockType && $blockType->variants !== []) {
            $current = $data[BlockShape::VARIANT_KEY] ?? null;

            if (! is_string($current) || $current === '') {
                $data[BlockShape::VARIANT_KEY] = $blockType->defaultVariant();
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
        $bindType = self::blockTypeFor($block)?->bind;

        if (! $bindType instanceof BindType) {
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

        return self::denyExecutableUrls($data);
    }

    /**
     * Drop URL values whose scheme executes when a browser follows them.
     *
     * Block data reaches `href`/`src` through Blade's `{{ }}`, which escapes the
     * VALUE but does nothing about the SCHEME — `javascript:alert(1)` in a
     * `cta_url` is a live link. Every authoring path feeds these fields:
     * {@see \App\Ai\BlockDataSanitizer} only `strip_tags()`es leaves (a no-op on
     * a scheme), the panel's {@see Fields\LinkInput}
     * deliberately allows relative paths and anchors, and seeders write raw
     * arrays. So the guard belongs at the one point every path funnels through
     * rather than at any single author — {@see resolveMediaUrls()} calls this for
     * the top-level node and once per repeater item.
     *
     * Unsetting rather than blanking is deliberate: every one of the
     * data-sourced href/src sites is wrapped in an `@if`, so a missing key
     * renders nothing at all instead of an empty `href=""` that reloads the page.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private static function denyExecutableUrls(array $data): array
    {
        foreach ($data as $key => $value) {
            if (! self::isDeniedUrl($key, $value)) {
                continue;
            }

            Log::warning('block.url_denied', ['key' => $key]);

            unset($data[$key]);
        }

        return $data;
    }

    /**
     * Whether one `data` entry is a URL-carrying field holding an executable
     * scheme. Keyed on the naming convention the views read — `url` on repeater
     * items, `*_url` for top-level props like `cta_url` and `image_url`.
     */
    private static function isDeniedUrl(mixed $key, mixed $value): bool
    {
        return is_string($key)
            && is_string($value)
            && ($key === 'url' || str_ends_with($key, '_url'))
            && UrlScheme::isExecutable($value);
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
        $bind = $data[BlockShape::BIND_KEY] ?? null;
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
     * The contract for a stored block, or null when it cannot be rendered: a
     * malformed entry, or a type this app does not register (so never authorable
     * by a tenant). Resolved through the vocabulary rather than the Fabricator
     * facade, which collapses the `getPageBlockFromName()` + `is_subclass_of()`
     * pair this class used to repeat three times.
     *
     * @param  array<string, mixed>  $block
     */
    private static function blockTypeFor(array $block): ?BlockType
    {
        $type = self::blockType($block);

        return $type === null ? null : resolve(BlockVocabulary::class)->get($type);
    }
}
