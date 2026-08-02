<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Locations\Schemas;

use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\OpeningHours\Exceptions\Exception as OpeningHoursException;
use Spatie\OpeningHours\OpeningHours;

final class LocationForm
{
    private const string TIME_RANGES_PATTERN = '/^(?:[01]\d|2[0-4]):[0-5]\d\-(?:[01]\d|2[0-4]):[0-5]\d(?:\s*,\s*(?:[01]\d|2[0-4]):[0-5]\d\-(?:[01]\d|2[0-4]):[0-5]\d)*$/';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Location')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('label')
                                ->required()
                                ->maxLength(255)
                                ->columnSpan(1),
                            Toggle::make('is_primary')
                                ->helperText('Shown on blocks that are not bound to a specific location.')
                                ->inline(false)
                                ->columnSpan(1),
                            TextInput::make('address_line1')
                                ->maxLength(255)
                                ->columnSpan(1),
                            TextInput::make('address_line2')
                                ->maxLength(255)
                                ->columnSpan(1),
                            TextInput::make('city')
                                ->maxLength(255)
                                ->columnSpan(1),
                            TextInput::make('state')
                                ->maxLength(255)
                                ->columnSpan(1),
                            TextInput::make('postal_code')
                                ->maxLength(32)
                                ->columnSpan(1),
                            TextInput::make('country')
                                ->maxLength(2)
                                ->helperText('ISO 3166-1 alpha-2, e.g. US')
                                ->columnSpan(1),
                            TextInput::make('latitude')
                                ->numeric()
                                // Clear the global 255 default: on numeric inputs it
                                // becomes max_digits, which rejects decimal points.
                                ->maxLength(null)
                                ->minValue(-90)
                                ->maxValue(90)
                                ->columnSpan(1),
                            TextInput::make('longitude')
                                ->numeric()
                                ->maxLength(null)
                                ->minValue(-180)
                                ->maxValue(180)
                                ->columnSpan(1),
                            TextInput::make('phone')
                                ->maxLength(255)
                                ->columnSpan(1),
                            TextInput::make('email')
                                ->email()
                                ->maxLength(255)
                                ->columnSpan(1),
                            TextInput::make('google_place_id')
                                ->label('Google place ID')
                                ->maxLength(255)
                                ->columnSpanFull()
                                ->helperText('Find it by searching your business on Google\'s Place ID finder. It is what turns on the "leave us a review" link and QR code — the single best thing a local business can do for how it ranks.'),
                            Select::make('timezone')
                                ->options(array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                                ->searchable()
                                ->columnSpan(1),
                            Select::make('status')
                                ->options([
                                    'active' => 'Active',
                                    'inactive' => 'Inactive',
                                ])
                                ->default('active')
                                ->selectablePlaceholder(false)
                                ->columnSpan(1),
                        ]),
                    ]),
                Section::make('Opening hours')
                    ->description('Comma-separated time ranges per day, e.g. "09:00-12:00, 13:00-17:00". Leave a day blank for closed.')
                    ->schema([
                        Grid::make(2)->schema(array_map(
                            self::dayField(...),
                            ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                        )),
                    ]),
            ]);
    }

    private static function dayField(string $day): TextInput
    {
        return TextInput::make('hours.'.$day)
            ->label(ucfirst($day))
            ->placeholder('09:00-17:00')
            ->regex(self::TIME_RANGES_PATTERN)
            ->rule(fn (): Closure => self::validRangesRule())
            ->columnSpan(1);
    }

    /**
     * Catches range sets the regex cannot judge (e.g. overlapping ranges) by
     * letting spatie/opening-hours parse the single day, so the error lands on
     * the exact field that caused it.
     */
    private static function validRangesRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            try {
                if (is_string($value) && mb_trim($value) !== '') {
                    OpeningHours::create(['monday' => array_map(trim(...), explode(',', $value))]);
                }
            } catch (OpeningHoursException $openingHoursException) {
                $fail($openingHoursException->getMessage());
            }
        };
    }
}
