<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * How far a review ask has got.
 *
 * Deliberately short of `converted`: whether a customer actually left a review can
 * only be known by reading the reviews back off Google, which needs the API
 * approval this feature exists to not wait for. Claiming it from a click would be
 * inventing a number, and an owner who catches the dashboard lying once stops
 * believing the rest of it.
 */
enum ReviewRequestStatus: string implements HasColor, HasLabel
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Clicked = 'clicked';

    public function getLabel(): string
    {
        return match ($this) {
            self::Queued => __('Ready'),
            self::Sent => __('Handed out'),
            self::Clicked => __('Opened'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Queued => 'gray',
            self::Sent => 'info',
            self::Clicked => 'success',
        };
    }
}
