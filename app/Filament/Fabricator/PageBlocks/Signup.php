<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\PageBlocks;

use App\Enums\LeadFieldSet;
use App\Filament\Fabricator\Fields\CaptureFields;
use App\Site\Blocks\BlockIntent;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * A short capture form placed mid-page, where the visitor has just been told
 * why the business is worth contacting.
 *
 * The contact block is the same idea at the other end of the funnel: four
 * fields, at the bottom, for someone who arrived intending to enquire. This
 * one exists because most visitors never get that far — it asks for one or two
 * fields in exchange for a stated offer, and it goes directly after the
 * section that made the case (the menu, the price list, the before-and-after
 * gallery).
 *
 * Content-only, like {@see Cta}: it declares no bind, and the lead's context
 * comes from the page it was submitted on.
 */
final class Signup extends Block
{
    protected static string $name = 'signup';

    protected static string $description = 'A short capture form offering something in return — a quote, a discount, a callback — placed right after the section that makes the case. Use it mid-page; use `contact` for the full enquiry form with address and hours, and `cta` when the next step is a link rather than a form.';

    protected static ?Heroicon $icon = Heroicon::OutlinedEnvelope;

    protected static ?BlockIntent $intent = BlockIntent::Convert;

    /**
     * @var array<string, mixed>
     */
    protected static array $sample = [
        'heading' => 'Get 10% off your first visit',
        'offer' => 'Leave your number and we will text you the voucher.',
        'fields' => 'phone',
        'button_label' => 'Send my voucher',
        'fine_print' => 'No spam — just the voucher.',
    ];

    /**
     * @var array<string, string>
     */
    protected static array $variants = [
        'banner' => 'Form beside the offer',
        'stacked' => 'Form below the offer',
    ];

    /**
     * @var array<string, string>
     */
    protected static array $axes = [
        'tone' => 'accent',
        'spacing' => 'tight',
        'width' => 'normal',
        'align' => 'start',
    ];

    /**
     * @var array<string, array<string, string>>
     */
    protected static array $variantAxes = [
        'stacked' => ['align' => 'center', 'width' => 'narrow'],
    ];

    /**
     * @return array<int, Field>
     */
    protected static function fields(): array
    {
        return [
            TextInput::make('heading')
                ->required()
                ->maxLength(200),
            CaptureFields::offer()
                ->helperText('The reason to hand over a phone number. A form with no offer is just a chore.'),
            CaptureFields::fields(LeadFieldSet::Phone)
                ->helperText('Fewer fields, more completions. Ask only for what you will actually use to reply.')
                ->required(),
            CaptureFields::buttonLabel()
                ->required(),
            CaptureFields::successMessage()
                ->placeholder('Thanks — we got it and will be in touch.'),
            CaptureFields::finePrint()
                ->helperText('A short reassurance under the button.'),
        ];
    }
}
