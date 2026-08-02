<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Publication state of an update. Deliberately the same two cases as
 * {@see PageStatus}: drafts are visible in the panel and 404 on the public site.
 *
 * No `Scheduled` and no `Expired`, and that is a scope decision rather than an
 * omission. Both need something to flip them — a scheduler this app does not
 * have yet — and a status that only a cron can leave would sit wrong forever the
 * first time a worker was down. Expiry is instead computed from `ends_at`
 * ({@see \App\Models\Post::isExpired()}), so it is always true the moment it is
 * true, with nothing to run and nothing to drift.
 */
enum PostStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Published = 'published';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Published => __('Published'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Published => 'success',
        };
    }
}
