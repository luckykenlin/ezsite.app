<?php

declare(strict_types=1);

use App\Site\OnboardingProgress;
use App\Site\OnboardingTask;

test('remaining lists the outstanding tasks in declaration order', function (): void {
    $progress = new OnboardingProgress([
        OnboardingTask::PublishSite->value => true,
        OnboardingTask::SiteAddress->value => false,
        OnboardingTask::PhoneNumber->value => true,
        OnboardingTask::Logo->value => false,
        OnboardingTask::CaptureSurface->value => false,
    ]);

    expect($progress->remaining())->toBe([
        OnboardingTask::SiteAddress,
        OnboardingTask::Logo,
        OnboardingTask::CaptureSurface,
    ])
        ->and($progress->isComplete())->toBeFalse()
        ->and($progress->isLive())->toBeTrue();
});

test('a site with every task done is complete and has nothing remaining', function (): void {
    $done = [];

    foreach (OnboardingTask::cases() as $task) {
        $done[$task->value] = true;
    }

    $progress = new OnboardingProgress($done);

    expect($progress->isComplete())->toBeTrue()
        ->and($progress->remaining())->toBeEmpty();
});

test('a task missing from the map counts as outstanding', function (): void {
    // A new enum case added after a map was built must under-report progress,
    // never silently mark the new work finished.
    $progress = new OnboardingProgress([]);

    expect($progress->isDone(OnboardingTask::Logo))->toBeFalse()
        ->and($progress->isLive())->toBeFalse()
        ->and($progress->remaining())->toBe(OnboardingTask::cases());
});
