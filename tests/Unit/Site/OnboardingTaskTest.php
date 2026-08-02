<?php

declare(strict_types=1);

use App\Site\OnboardingTask;

test('publishing is the first thing asked for', function (): void {
    // Order is a product decision, not an accident: publishing is the only task
    // that costs customers rather than polish, so it heads the list and it is
    // the one the panel-wide banner watches.
    expect(OnboardingTask::cases()[0])->toBe(OnboardingTask::PublishSite);
});

test('every task reads as its own distinct instruction with its own reason', function (): void {
    $labels = array_map(fn (OnboardingTask $task): string => $task->label(), OnboardingTask::cases());
    $reasons = array_map(fn (OnboardingTask $task): string => $task->why(), OnboardingTask::cases());

    // Calling both for every case also proves the two matches are exhaustive: a
    // case added without copy throws UnhandledMatchError right here, which is
    // the failure mode worth catching — a blank row on the owner's dashboard.
    expect($labels)->not->toContain('')
        ->and(array_unique($labels))->toHaveCount(count($labels))
        ->and($reasons)->not->toContain('')
        ->and(array_unique($reasons))->toHaveCount(count($reasons));
});
