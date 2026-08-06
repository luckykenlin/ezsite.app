<?php

declare(strict_types=1);

namespace App\Site;

/**
 * How far a site's owner has got through {@see OnboardingTask}.
 *
 * Resolved once per request from the container (a `scoped` binding registered in
 * AppServiceProvider), because three surfaces ask the same question on the same
 * page load — the panel-wide banner, the dashboard checklist, and the checklist's
 * own `canView()` — and each answer costs a handful of queries.
 *
 * @see \App\Actions\BuildOnboardingProgress builds one
 */
final readonly class OnboardingProgress
{
    /**
     * @param  array<string, bool>  $done  keyed by {@see OnboardingTask} value
     */
    public function __construct(private array $done) {}

    /**
     * An unknown task counts as outstanding: a map built before a case was added
     * should under-report progress rather than silently mark new work finished.
     */
    public function isDone(OnboardingTask $task): bool
    {
        return $this->done[$task->value] ?? false;
    }

    /**
     * Whether the public site answers at all — the one distinction urgent enough
     * to earn a persistent banner rather than a row on a checklist.
     */
    public function isLive(): bool
    {
        return $this->isDone(OnboardingTask::PublishSite);
    }

    /**
     * @return list<OnboardingTask>
     */
    public function remaining(): array
    {
        return array_values(array_filter(
            OnboardingTask::cases(),
            fn (OnboardingTask $task): bool => ! $this->isDone($task),
        ));
    }

    public function isComplete(): bool
    {
        return $this->remaining() === [];
    }
}
