<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\PageBlocks;

use App\Enums\BindType;
use App\Filament\Fabricator\Fields\CaptureFields;
use App\Site\Blocks\BlockIntent;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * A table-booking request form: name and phone plus the three facts a
 * restaurant needs — date, time, party size.
 *
 * A request, deliberately not a booking SYSTEM: there is no capacity model, no
 * confirm/decline state, no table plan. The submission lands in the same Leads
 * inbox as every enquiry (as {@see \App\Enums\LeadSource::Reservation}) and the
 * operator confirms by phone, which is how the restaurants this serves already
 * work. Growing real availability later means a `reservations` table and its
 * own machinery — nothing here forecloses that.
 *
 * Location-bound like {@see Contact}: the request is FOR a place, and the
 * bound location's timezone is what "no dates in the past" is judged against
 * at capture time.
 */
final class Reservation extends Block
{
    protected static string $name = 'reservation';

    protected static string $description = 'A table-booking request form asking for a date, time and party size, delivered to the inbox like any enquiry. Use it on restaurant pages where visitors book a table; use `contact` for a general enquiry form, and `cta` when booking happens on an external service.';

    protected static ?Heroicon $icon = Heroicon::OutlinedCalendarDays;

    protected static ?BlockIntent $intent = BlockIntent::Convert;

    protected static ?BindType $bindType = BindType::Location;

    /**
     * @var array<string, mixed>
     */
    protected static array $sample = [
        'heading' => 'Book a table',
        'intro' => 'Tell us when and how many — we confirm every request by phone.',
        'max_party_size' => 8,
        'button_label' => 'Request a table',
        'fine_print' => 'Larger group? Call us and we will set the back room.',
    ];

    /**
     * Narrow on purpose: a booking form is one decision, and a full-width
     * form on a desktop viewport reads as a spreadsheet.
     *
     * @var array<string, string>
     */
    protected static array $axes = [
        'tone' => 'base',
        'spacing' => 'normal',
        'width' => 'narrow',
        'align' => 'start',
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
                ->maxLength(300),
            TextInput::make('max_party_size')
                ->label('Largest party bookable online')
                ->numeric()
                ->minValue(1)
                ->maxValue(50)
                ->helperText('Bigger groups are asked to call instead — say so in the fine print.'),
            CaptureFields::buttonLabel(),
            CaptureFields::successMessage()
                ->placeholder('Request received — we will confirm shortly.'),
            CaptureFields::finePrint()
                ->helperText('One line under the button — the place to say what happens to larger groups.'),
        ];
    }
}
