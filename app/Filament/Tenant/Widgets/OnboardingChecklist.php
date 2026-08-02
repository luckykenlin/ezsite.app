<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Widgets;

use App\Filament\Tenant\Pages\BusinessProfile;
use App\Filament\Tenant\Pages\CaptureSettings;
use App\Filament\Tenant\Resources\Locations\LocationResource;
use App\Filament\Tenant\Resources\PageResource;
use App\Site\OnboardingProgress;
use App\Site\OnboardingTask;
use Filament\Widgets\Widget;

/**
 * The dashboard's "finish setting up your site" list.
 *
 * The panel's first screen used to be Filament's two stock widgets — the
 * account card and a link to Filament's own documentation — which told the
 * owner of a new site nothing about what was left to do. Every row here is
 * computed from stored state and links to the screen that fixes it.
 *
 * It removes itself once every task is done ({@see canView()}) instead of
 * settling into a permanent row of ticks: a checklist that never goes away
 * stops being read.
 *
 * The URL for each task lives here rather than on {@see OnboardingTask},
 * because a route is panel knowledge and `App\Site` must not import
 * `App\Filament` (tests/Arch/LayeringTest).
 */
final class OnboardingChecklist extends Widget
{
    protected string $view = 'filament.tenant.widgets.onboarding-checklist';

    protected int|string|array $columnSpan = 'full';

    /**
     * Ahead of the account widget: what is unfinished matters more than who is
     * signed in.
     */
    protected static ?int $sort = -1;

    public static function canView(): bool
    {
        return ! resolve(OnboardingProgress::class)->isComplete();
    }

    /**
     * Where an owner goes to finish a task. `match` without a default, so a new
     * case cannot be added without deciding where it points.
     */
    public static function urlFor(OnboardingTask $task): string
    {
        return match ($task) {
            OnboardingTask::PublishSite => PageResource::getUrl('index'),
            OnboardingTask::SiteAddress => LocationResource::getUrl('index'),
            OnboardingTask::PhoneNumber, OnboardingTask::Logo => BusinessProfile::getUrl(),
            OnboardingTask::CaptureSurface => CaptureSettings::getUrl(),
        };
    }

    /**
     * @return list<array{task: OnboardingTask, done: bool, url: string}>
     */
    public function steps(): array
    {
        $progress = resolve(OnboardingProgress::class);

        return array_map(fn (OnboardingTask $task): array => [
            'task' => $task,
            'done' => $progress->isDone($task),
            'url' => self::urlFor($task),
        ], OnboardingTask::cases());
    }

    public function remainingCount(): int
    {
        return count(resolve(OnboardingProgress::class)->remaining());
    }
}
