<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\PageBlocks;

use App\Filament\Fabricator\Fields\ImageInput;
use App\Site\Blocks\BlockIntent;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * The people behind the business.
 *
 * Content-only, deliberately: staff are not a record this app models, so there is
 * no bind target. If team members ever become rows, this block gains a bind and
 * the fields stay where they are.
 *
 * `avatar_media_id` rather than `image_id`, matching {@see Testimonials}: the
 * media-key convention in {@see \App\Filament\Fabricator\BlockRegistry} resolves
 * that name into an `avatar_url` prop, so the view reads the same key whether the
 * photo came from the media library or was pasted as a URL. A member with no
 * photo renders initials instead — a broken image frame is worse than no frame.
 */
final class Team extends Block
{
    protected static string $name = 'team';

    protected static string $description = 'The people a customer will actually deal with — names, roles and a line each. Never invent a person: write these only from names the operator gave you, and leave the block with its placeholder if they gave none.';

    protected static ?Heroicon $icon = Heroicon::OutlinedUserGroup;

    protected static ?BlockIntent $intent = BlockIntent::Trust;

    /**
     * @var array<string, mixed>
     */
    protected static array $sample = [
        'heading' => 'Meet the team',
        'members' => [
            ['name' => 'Add a real name', 'role' => 'Their role', 'bio' => 'Replace this with a sentence about them.'],
        ],
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
        'item_style' => 'plain',
        'image_shape' => 'circle',
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
            Repeater::make('members')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(120),
                    TextInput::make('role')
                        ->maxLength(120),
                    Textarea::make('bio')
                        ->rows(2)
                        ->maxLength(500),
                    ImageInput::make('avatar_media_id')
                        ->label('Photo'),
                    TextInput::make('avatar_url')
                        ->label('External photo URL')
                        ->maxLength(2048),
                ])
                ->addActionLabel('Add person'),
        ];
    }
}
