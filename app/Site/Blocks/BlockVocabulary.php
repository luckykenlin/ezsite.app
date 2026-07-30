<?php

declare(strict_types=1);

namespace App\Site\Blocks;

/**
 * The enumerated set of block types the app knows about — the boundary that keeps
 * blocks *selectable but never authorable*.
 *
 * This is the domain's block contract, and it is what the AI layer composes from.
 * It used to be `App\Filament\Fabricator\BlockRegistry::vocabulary()`, which meant
 * `app/Ai`, `app/Actions` and even `app/Models` imported the admin-panel namespace
 * to reach a business rule. The block CLASSES still live under `App\Filament`
 * (Fabricator globs them from there, and they are genuinely Filament form
 * schemas), but the enumeration they produce no longer drags the panel along:
 * {@see \App\Providers\AppServiceProvider} builds this from them once per request
 * and binds it, so the wiring lives in the one layer allowed to see both sides.
 *
 * Scoped rather than singleton: block discovery is per-request in the panel, and a
 * queue worker handling two tenants' turns must not share a stale enumeration.
 */
final readonly class BlockVocabulary
{
    /**
     * @param  array<string, BlockType>  $types  keyed by block type name
     */
    public function __construct(private array $types)
    {
        //
    }

    /**
     * Every known type, chrome included. Only the RENDER path wants this — it has
     * to render headers and footers too.
     *
     * @return array<string, BlockType>
     */
    public function all(): array
    {
        return $this->types;
    }

    /**
     * The types that belong in a page body. Anything choosing types for a human or
     * for the model reads this, so the chrome exclusion has one definition.
     *
     * @return array<string, BlockType>
     */
    public function pageTypes(): array
    {
        return array_filter($this->types, static fn (BlockType $type): bool => ! $type->isChrome());
    }

    /**
     * The page-level type NAMES, for schema enums and prompt vocabularies.
     *
     * @return list<string>
     */
    public function pageTypeNames(): array
    {
        return array_keys($this->pageTypes());
    }

    public function get(string $type): ?BlockType
    {
        return $this->types[$type] ?? null;
    }

    public function has(string $type): bool
    {
        return array_key_exists($type, $this->types);
    }

    /**
     * Whether a type may be added to a page: known, and not site chrome.
     */
    public function isAddableToPage(string $type): bool
    {
        return $this->get($type)?->isChrome() === false;
    }
}
