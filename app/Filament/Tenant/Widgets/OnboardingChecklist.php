<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Widgets;

use App\Filament\Tenant\Support\OnboardingTaskUrls;
use App\Site\OnboardingProgress;
use App\Site\OnboardingTask;
use Filament\Widgets\Widget;

/**
 * The dashboard's "finish setting up your site" list.
 *
 * The panel's first screen used to be Filament's two stock widgets — the
 * account card and a link to Filament's own documentation — which told the
 * owner of a new site nothing about what was left to do. Every row here is
 * computed from stored state ({@see OnboardingProgress}) and links to the
 * screen that fixes it ({@see OnboardingTaskUrls}).
 *
 * It removes itself once every task is done ({@see canView()}) instead of
 * settling into a permanent row of ticks: a checklist that never goes away
 * stops being read.
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
     * @return list<array{task: OnboardingTask, done: bool, url: string}>
     */
    public function steps(): array
    {
        $progress = $this->progress();

        return array_map(fn (OnboardingTask $task): array => [
            'task' => $task,
            'done' => $progress->isDone($task),
            'url' => OnboardingTaskUrls::for($task),
        ], OnboardingTask::cases());
    }

    public function remainingCount(): int
    {
        return count($this->progress()->remaining());
    }

    /**
     * The container is the memo here, on purpose: `OnboardingProgress` is a
     * SCOPED binding whose closure runs {@see \App\Actions\BuildOnboardingProgress}
     * once per request, shared with `canView()` and the site-not-live banner.
     * Resolving per call is how a Livewire widget stays stateless while still
     * paying for the four queries only once.
     */
    private function progress(): OnboardingProgress
    {
        return resolve(OnboardingProgress::class);
    }
}
