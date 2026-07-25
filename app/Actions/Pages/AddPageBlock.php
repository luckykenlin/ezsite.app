<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Filament\Fabricator\PageBlocks\Block;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Z3d0X\FilamentFabricator\Facades\FilamentFabricator;

/**
 * Appends (or inserts) a new block into an editor blocks list.
 *
 * The blocks list is the page editor's working state: the persisted
 * `[{type, data}]` shape plus a transient `key` (uuid) per entry that gives
 * each block a stable identity for canvas selection. This action is pure
 * (arrays in, arrays out) so the phase-2 AI tools can call it against the
 * same state the human editor uses. Unknown types fail LOUD, mirroring
 * {@see \App\Actions\UpdateDesignTokens}: a misbehaving caller gets an
 * exception, never a silent no-op.
 */
final readonly class AddPageBlock
{
    /**
     * @param  list<array{key: string, type: string, data: array<string, mixed>}>  $blocks
     * @return array{blocks: list<array{key: string, type: string, data: array<string, mixed>}>, key: string}
     */
    public function handle(array $blocks, string $type, ?int $position = null): array
    {
        $class = FilamentFabricator::getPageBlockFromName($type);

        throw_unless(
            is_string($class) && is_subclass_of($class, Block::class),
            InvalidArgumentException::class,
            sprintf('Unknown block type [%s].', $type),
        );

        $key = (string) Str::uuid();

        $block = [
            'key' => $key,
            'type' => $type,
            'data' => $class::variants() === [] ? [] : [Block::VARIANT_KEY => $class::defaultVariant()],
        ];

        $position = min(max($position ?? count($blocks), 0), count($blocks));

        return [
            'blocks' => [
                ...array_slice($blocks, 0, $position),
                $block,
                ...array_slice($blocks, $position),
            ],
            'key' => $key,
        ];
    }
}
