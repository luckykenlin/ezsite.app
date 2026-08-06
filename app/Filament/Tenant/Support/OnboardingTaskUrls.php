<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Support;

use App\Filament\Tenant\Pages\BusinessProfile;
use App\Filament\Tenant\Pages\CaptureSettings;
use App\Filament\Tenant\Resources\Locations\LocationResource;
use App\Filament\Tenant\Resources\PageResource;
use App\Site\OnboardingTask;

/**
 * Where an owner goes to finish each onboarding task.
 *
 * Lives on the panel side rather than on {@see OnboardingTask}, because a
 * route is panel knowledge and `App\Site` must not import `App\Filament`
 * (tests/Arch/LayeringTest) — and as its own class rather than a static on
 * the dashboard widget, because it is a routing table, not widget state.
 * `match` without a default, so a new task cannot be added without deciding
 * where it points.
 */
final readonly class OnboardingTaskUrls
{
    public static function for(OnboardingTask $task): string
    {
        // Assigned before returning: parallel coverage mis-marks a bare
        // `return match (…) {` line as uncovered.
        $url = match ($task) {
            OnboardingTask::PublishSite => PageResource::getUrl('index'),
            OnboardingTask::SiteAddress => LocationResource::getUrl('index'),
            OnboardingTask::PhoneNumber, OnboardingTask::Logo => BusinessProfile::getUrl(),
            OnboardingTask::CaptureSurface => CaptureSettings::getUrl(),
        };

        return $url;
    }
}
