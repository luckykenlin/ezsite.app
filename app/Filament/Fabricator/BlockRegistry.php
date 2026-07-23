<?php

declare(strict_types=1);

namespace App\Filament\Fabricator;

use App\Filament\Fabricator\PageBlocks\Block;
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
     * Every registered block's machine-readable contract, keyed by type.
     *
     * @return array<string, array{type: string, variants: list<string>, bind: string|null, fields: list<string>}>
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
