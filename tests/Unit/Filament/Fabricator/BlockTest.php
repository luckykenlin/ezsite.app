<?php

declare(strict_types=1);

use App\Filament\Fabricator\PageBlocks\Contact;
use App\Filament\Fabricator\PageBlocks\Features;
use App\Filament\Fabricator\PageBlocks\Header;
use App\Filament\Fabricator\PageBlocks\Heading;
use App\Filament\Fabricator\PageBlocks\Hero;
use App\Models\Location;
use App\Models\Tenant;
use App\Site\Blocks\BlockShape;
use App\Site\Blocks\LayoutAxis;
use App\Site\Blocks\SectionSpacing;
use App\Site\Blocks\SectionTone;
use Filament\Forms\Components\Builder\Block;
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
function containerizedBlockComponents(Block $block): array
{
    $livewire = new class extends LivewireComponent implements HasSchemas
    {
        use InteractsWithSchemas;
    };

    return $block->container(Schema::make($livewire))->getChildComponents();
}

it('auto-injects a location picker ahead of the content fields on Location-bound blocks', function (): void {
    $tenant = Tenant::factory()->create();
    $this->runInTenant($tenant, fn (): Location => Location::factory()->create([
        'tenant_id' => $tenant->id,
        'label' => 'Main spot',
        'is_primary' => true,
    ]));

    $components = containerizedBlockComponents(Contact::getBlockSchema());

    // Contact is variant-less since the layout axes absorbed split/stacked,
    // so the bind select leads the schema.
    /** @var Select $bindSelect */
    $bindSelect = $components[0];

    expect($bindSelect)->toBeInstanceOf(Select::class)
        ->and($bindSelect->getName())->toBe(BlockShape::BIND_KEY.'.location_id')
        ->and($bindSelect->isRequired())->toBeFalse()
        ->and(array_values($bindSelect->getOptions()))->toBe(['Main spot'])
        ->and(array_map(fn (Field $field): string => $field->getName(), array_slice($components, 1)))
        // Appearance goes after the content, not before it: an operator opens a
        // block to write words, and empty selects should not stand between
        // them and the headline.
        ->toBe(['heading', 'intro', 'show_form', 'success_message', 'appearance.tone', 'appearance.spacing', 'appearance.width', 'appearance.align', 'appearance.columns']);

    // The auto-injected select is explicitly live WITHOUT a debounce, so
    // switching location refreshes the canvas immediately.
    expect($bindSelect->isLive())->toBeTrue()
        ->and($bindSelect->isLiveDebounced())->toBeFalse();
});

it('does not inject a location picker on Business-bound blocks', function (): void {
    $fieldNames = array_map(
        fn (Field $field): string => $field->getName(),
        containerizedBlockComponents(Header::getBlockSchema()),
    );

    // Chrome gets no appearance selects either, and for a reason of its own: a
    // header is the frame around every page, not a section in one page's
    // rhythm, so its view deliberately stays outside the section shell. Offering
    // the selects would offer a control that does nothing.
    expect($fieldNames)->toBe([BlockShape::VARIANT_KEY, 'nav_links', 'cta_label', 'cta_url']);
});

it("auto-injects a required variant selector ahead of a variant block's content fields", function (): void {
    $schema = Hero::getBlockSchema();
    $components = containerizedBlockComponents($schema);

    /** @var Select $variantSelect */
    $variantSelect = $components[0];

    expect($schema->getName())->toBe('hero')
        ->and($variantSelect)->toBeInstanceOf(Select::class)
        ->and($variantSelect->getName())->toBe(BlockShape::VARIANT_KEY)
        ->and($variantSelect->getOptions())->toBe(Hero::variants())
        ->and($variantSelect->getDefaultState())->toBe('centered-minimal')
        ->and($variantSelect->isRequired())->toBeTrue()
        ->and(array_map(fn (Field $field): string => $field->getName(), array_slice($components, 1)))
        ->toBe([
            'eyebrow', 'heading', 'subheading', 'cta_label', 'cta_url', 'image_id', 'image_url',
            'appearance.tone', 'appearance.spacing', 'appearance.width', 'appearance.align', 'appearance.image_shape',
        ]);
});

/*
 * The appearance selects nest into `data.appearance.{tone,spacing}` and are
 * OPTIONAL, which is the whole zero-regression contract: unset means "whatever
 * this layout was designed to do", i.e. exactly the value each view hard-coded
 * before the shell existed. Live without a debounce for the same reason as the
 * variant select — a background change is a high-contrast edit to sit and wait
 * half a second for.
 */
it('auto-injects optional, immediately-live appearance selects after the content fields', function (): void {
    $components = containerizedBlockComponents(Heading::getBlockSchema());

    /** @var Select $tone */
    $tone = $components[2];
    /** @var Select $spacing */
    $spacing = $components[3];

    expect($tone->getName())->toBe(BlockShape::APPEARANCE_KEY.'.'.BlockShape::TONE_KEY)
        ->and($tone->getOptions())->toBe(SectionTone::options())
        ->and($tone->isRequired())->toBeFalse()
        ->and($tone->getDefaultState())->toBeNull()
        ->and($tone->isLive())->toBeTrue()
        ->and($tone->isLiveDebounced())->toBeFalse()
        ->and($spacing->getName())->toBe(BlockShape::APPEARANCE_KEY.'.'.BlockShape::SPACING_KEY)
        ->and($spacing->getOptions())->toBe(SectionSpacing::options())
        ->and($spacing->isRequired())->toBeFalse()
        ->and($spacing->getDefaultState())->toBeNull()
        ->and($spacing->isLive())->toBeTrue()
        ->and($spacing->isLiveDebounced())->toBeFalse();
});

it('composes a no-variant block from its content fields only, without a variant selector', function (): void {
    $schema = Heading::getBlockSchema();
    $fieldNames = array_map(fn (Field $field): string => $field->getName(), containerizedBlockComponents($schema));

    expect($schema->getName())->toBe('heading')
        ->and($fieldNames)->toBe(['content', 'level', 'appearance.tone', 'appearance.spacing', 'appearance.width', 'appearance.align']);
});

it('injects one select per contract axis, options straight off the axis enum', function (): void {
    $components = containerizedBlockComponents(Features::getBlockSchema());

    $selects = [];

    foreach ($components as $component) {
        if ($component instanceof Select && str_starts_with($component->getName(), BlockShape::APPEARANCE_KEY.'.')) {
            $selects[mb_substr($component->getName(), mb_strlen(BlockShape::APPEARANCE_KEY) + 1)] = $component;
        }
    }

    // features declares the full roster, in LayoutAxis order.
    expect(array_keys($selects))->toBe(['tone', 'spacing', 'width', 'align', 'columns', 'item_style', 'image_shape']);

    foreach ($selects as $key => $select) {
        $axis = LayoutAxis::from($key);

        expect($select->getOptions())->toBe($axis->enumClass()::options())
            ->and($select->isRequired())->toBeFalse()
            ->and($select->getDefaultState())->toBeNull()
            // The clearing-= -reset contract: an empty select must dehydrate
            // to nothing, or every save writes null axes and manufactures
            // revisions.
            ->and($select->isDehydrated())->toBeFalse()
            ->and($select->isLive())->toBeTrue()
            ->and($select->isLiveDebounced())->toBeFalse();
    }
});
