<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\PageBlocks;

use App\Filament\Fabricator\Fields\ImageInput;
use App\Site\Blocks\BlockIntent;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;

/**
 * The things a business sells: a restaurant's menu, a salon's service list, a
 * studio's packages, a shop's products.
 *
 * ONE block for all of those rather than a type per industry. A menu item and a
 * salon service are the same record — a name, a price, a line of description,
 * optionally a photo, optionally grouped under a course or category — so
 * separate types would be four near-identical classes, four sets of views, and
 * four look-alike names for the assistant to choose between on every turn. What
 * differs between the industries is the CONTENT, and content is what the
 * operator and the model supply.
 *
 * `group` is a plain string on each item, deliberately flat rather than a
 * `groups → items` tree. Two reasons, and the second is the load-bearing one:
 *
 *  - The view groups by value at render time, so the operator reorders one flat
 *    repeater instead of dragging items between nested ones.
 *  - {@see \App\Ai\BlockDataSanitizer}'s field whitelist is TOP-LEVEL only, and
 *    its own docblock calls the nesting gap "currently inert rather than
 *    exploitable" — inert precisely because no block nests two deep. A
 *    `groups → items` shape would be the first to do so, and would make that
 *    documented non-problem a real one.
 *
 * `price` is a string, not a number. Real prices are "$12", "from £40", "POA",
 * "¥88 / person" — the framing carries meaning, currencies differ per tenant,
 * and a numeric column would force this block to own money formatting for every
 * locale. The operator types what their customers should read.
 *
 * Two layouts inside the default variant, which is a deliberate exception to "a
 * new block starts with one": a priced list and an image-led grid are not
 * speculation about what someone might want later, they are the two shapes this
 * block was asked for. A menu wants name-to-price with no photographs; a
 * product or package wants the photo to lead.
 *
 * The `menu-card` variant is the third shape, and it earns a variant rather
 * than another item_style because it changes the anatomy, not the styling: the
 * whole menu becomes one framed card, groups become filterable courses, and a
 * featured item gets a photographic spotlight. Dietary flags (`spicy`,
 * `vegetarian`, `gluten_free`) live on the item because they are facts about
 * the dish, not about the layout — every variant renders them.
 */
final class Offerings extends Block
{
    protected static string $name = 'offerings';

    protected static string $description = 'Things the business sells, with prices — menu dishes, salon or clinic services, packages, products. Use this whenever an item has a price; for reasons to choose the business, with no price, use features instead. Optionally group items (e.g. "Starters", "Colour") and they render under those headings. Items may carry dietary flags (spicy, vegetarian, gluten_free — booleans) and is_featured, which the menu-card variant renders as a photographic spotlight; the menu-card variant draws the whole menu as one framed card with course tabs, made for restaurants.';

    protected static ?Heroicon $icon = Heroicon::OutlinedTag;

    protected static ?BlockIntent $intent = BlockIntent::Showcase;

    /**
     * @var array<string, mixed>
     */
    protected static array $sample = [
        'heading' => 'What we offer',
        'items' => [
            ['group' => 'Popular', 'name' => 'Your first item', 'price' => '$00', 'description' => 'Replace this with what the customer gets.'],
            ['group' => 'Popular', 'name' => 'Your second item', 'price' => '$00', 'description' => 'Replace this with what the customer gets.'],
        ],
    ];

    /**
     * `simple` first: every offerings block stored before variants existed has
     * no variant key, and the default (first declared) is what keeps them — and
     * every template definition that says only `'type' => 'offerings'` —
     * rendering exactly as they did.
     *
     * @var array<string, string>
     */
    protected static array $variants = [
        'simple' => 'Simple — plain priced list or cards',
        'menu-card' => 'Menu card — the whole menu as one framed card, with course tabs',
    ];

    /**
     * @var array<string, string>
     */
    protected static array $axes = [
        'tone' => 'base',
        'spacing' => 'normal',
        'width' => 'wide',
        'align' => 'center',
        'columns' => 'three',
        'item_style' => 'card',
        'image_shape' => 'wide',
    ];

    /**
     * The menu card is always a two-up printed menu inside its frame: rows, not
     * cards (the frame is the card), wide because the card supplies its own
     * measure, centred because that is how a menu is set.
     *
     * @var array<string, array<string, string>>
     */
    protected static array $variantAxes = [
        'menu-card' => [
            'spacing' => 'airy',
            'width' => 'wide',
            'align' => 'center',
            'columns' => 'two',
            'item_style' => 'plain',
        ],
    ];

    /**
     * @return array<int, Field>
     */
    protected static function fields(): array
    {
        return [
            TextInput::make('heading')
                ->maxLength(200),
            Textarea::make('intro')
                ->rows(2)
                ->maxLength(500),
            Repeater::make('items')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(160),
                    TextInput::make('price')
                        ->maxLength(24)
                        ->helperText('Written exactly as customers should read it — "$12", "from £40", "POA".'),
                    Textarea::make('description')
                        ->rows(2)
                        ->maxLength(500),
                    TextInput::make('group')
                        ->label('Group')
                        ->maxLength(80)
                        ->helperText('Optional — items sharing a group render under one heading, e.g. "Starters".'),
                    ImageInput::make('image_id'),
                    Toggle::make('spicy'),
                    Toggle::make('vegetarian'),
                    Toggle::make('gluten_free'),
                    Toggle::make('is_featured')
                        ->label('Feature this item')
                        ->helperText('The menu card spotlights one featured item with its photo — only the first one carries.'),
                ])
                ->addActionLabel('Add item'),
        ];
    }
}
