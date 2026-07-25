<?php

declare(strict_types=1);

use App\Enums\BindType;
use App\Filament\Fabricator\PageBlocks\Block;
use App\Filament\Fabricator\PageBlocks\Contact;
use App\Filament\Fabricator\PageBlocks\Cta;
use App\Filament\Fabricator\PageBlocks\Features;
use App\Filament\Fabricator\PageBlocks\Footer;
use App\Filament\Fabricator\PageBlocks\Gallery;
use App\Filament\Fabricator\PageBlocks\Header;
use App\Filament\Fabricator\PageBlocks\Heading;
use App\Filament\Fabricator\PageBlocks\Hero;
use App\Filament\Fabricator\PageBlocks\Testimonials;
use App\Models\Location;
use App\Models\Tenant;
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
        ->and(Heading::bindType())->toBeNull()
        ->and(Features::bindType())->toBeNull()
        ->and(Testimonials::bindType())->toBeNull()
        ->and(Gallery::bindType())->toBeNull()
        ->and(Cta::bindType())->toBeNull();
});

it('declares the bind target of factual blocks in their contracts', function (): void {
    expect(Header::bindType())->toBe(BindType::Business)
        ->and(Contact::bindType())->toBe(BindType::Location)
        ->and(Footer::bindType())->toBe(BindType::Location)
        ->and(Header::contract()['bind'])->toBe('business')
        ->and(Contact::contract()['bind'])->toBe('location')
        ->and(Footer::contract()['bind'])->toBe('location');
});

it('auto-injects a location picker between the variant selector and content fields on Location-bound blocks', function (): void {
    $tenant = Tenant::factory()->create();
    $this->runInTenant($tenant, fn (): Location => Location::factory()->create([
        'tenant_id' => $tenant->id,
        'label' => 'Main spot',
        'is_primary' => true,
    ]));

    $components = containerizedBlockComponents(Contact::getBlockSchema());

    /** @var Select $bindSelect */
    $bindSelect = $components[1];

    expect($components[0]->getName())->toBe(Block::VARIANT_KEY)
        ->and($bindSelect)->toBeInstanceOf(Select::class)
        ->and($bindSelect->getName())->toBe(Block::BIND_KEY.'.location_id')
        ->and($bindSelect->isRequired())->toBeFalse()
        ->and(array_values($bindSelect->getOptions()))->toBe(['Main spot'])
        ->and(array_map(fn (Field $field): string => $field->getName(), array_slice($components, 2)))
        ->toBe(['heading', 'intro']);
});

it('does not inject a location picker on Business-bound blocks', function (): void {
    $fieldNames = array_map(
        fn (Field $field): string => $field->getName(),
        containerizedBlockComponents(Header::getBlockSchema()),
    );

    expect($fieldNames)->toBe([Block::VARIANT_KEY, 'nav_links', 'cta_label', 'cta_url']);
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
