<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * An enquiry's place in the operator's inbox: unread, seen, or filed away.
 * Deliberately not a sales pipeline — following up happens off-platform.
 */
enum LeadStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case Read = 'read';
    case Archived = 'archived';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'success',
            self::Read => 'gray',
            self::Archived => 'warning',
        };
    }
}
