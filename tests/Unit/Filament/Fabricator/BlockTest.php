<?php

declare(strict_types=1);

use App\Filament\Fabricator\PageBlocks\Block;
use App\Filament\Fabricator\PageBlocks\Heading;
use App\Filament\Fabricator\PageBlocks\Hero;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component as LivewireComponent;

/**
 * Filament component getters resolve through their schema container, so give the
 * built block a minimal standalone host before introspecting its children.
 */
function containerizedBlockComponents(Filament\Forms\Components\Builder\Block $block): array
{
    $livewire = new class extends LivewireComponent implements HasSchemas
    {
        use InteractsWithSchemas;
    };

    return $block->container(Schema::make($livewire))->getChildComponents();
}

it('declares no bind type for content-only blocks', function (): void {
    expect(Hero::bindType())->toBeNull()
        ->and(Heading::bindType())->toBeNull();
});

it("auto-injects a required variant selector ahead of a variant block's content fields", function (): void {
    $schema = Hero::getBlockSchema();
    $components = containerizedBlockComponents($schema);

    /** @var Select $variantSelect */
    $variantSelect = $components[0];

    expect($schema->getName())->toBe('hero')
        ->and($variantSelect)->toBeInstanceOf(Select::class)
        ->and($variantSelect->getName())->toBe(Block::VARIANT_KEY)
        ->and($variantSelect->getOptions())->toBe(Hero::variants())
        ->and($variantSelect->getDefaultState())->toBe('centered-minimal')
        ->and($variantSelect->isRequired())->toBeTrue()
        ->and(array_map(fn (Field $field): string => $field->getName(), array_slice($components, 1)))
        ->toBe(['eyebrow', 'heading', 'subheading', 'cta_label', 'cta_url', 'image_url']);
});

it('composes a no-variant block from its content fields only, without a variant selector', function (): void {
    $schema = Heading::getBlockSchema();
    $fieldNames = array_map(fn (Field $field): string => $field->getName(), containerizedBlockComponents($schema));

    expect($schema->getName())->toBe('heading')
        ->and($fieldNames)->toBe(['content', 'level']);
});
