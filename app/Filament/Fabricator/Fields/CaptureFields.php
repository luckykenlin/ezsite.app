<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\Fields;

use App\Enums\LeadFieldSet;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * The capture-form field skeletons that the {@see \App\Filament\Fabricator\PageBlocks\Signup}
 * block and the popup half of {@see \App\Filament\Tenant\Pages\CaptureSettings}
 * share: same names, labels and limits, because both write the shape one
 * renderer reads.
 *
 * Skeletons, deliberately — each caller chains its own helper text, defaults
 * and required rules, since the advice that fits a mid-page block differs
 * from what fits the site-wide popup. What must NOT drift (the label copy and
 * the column-width limits) lives here once.
 *
 * The popup passes its nested state path (`popup.offer`); the block uses the
 * bare name.
 */
final class CaptureFields
{
    /**
     * The reassurance line both surfaces suggest — and the render fallback
     * they preview.
     */
    public const string FINE_PRINT_PLACEHOLDER = 'No spam. Unsubscribe anytime.';

    public static function offer(string $name = 'offer'): Textarea
    {
        return Textarea::make($name)
            ->label('What they get')
            ->rows(2)
            ->maxLength(300);
    }

    public static function fields(LeadFieldSet $default, string $name = 'fields'): Select
    {
        return Select::make($name)
            ->label('Ask for')
            ->options(LeadFieldSet::options())
            ->default($default->value)
            ->selectablePlaceholder(false);
    }

    public static function buttonLabel(string $name = 'button_label'): TextInput
    {
        return TextInput::make($name)
            ->label('Button')
            ->maxLength(60);
    }

    public static function successMessage(string $name = 'success_message'): TextInput
    {
        return TextInput::make($name)
            ->label('Thank-you message')
            ->maxLength(200);
    }

    public static function finePrint(string $name = 'fine_print'): TextInput
    {
        return TextInput::make($name)
            ->label('Fine print')
            ->placeholder(self::FINE_PRINT_PLACEHOLDER)
            ->maxLength(120);
    }
}
