<?php

declare(strict_types=1);

use App\Jobs\ChatEditPageJob;
use App\Jobs\GenerateSiteDraftJob;
use Illuminate\Queue\Attributes\Timeout;

/*
 * Fail-CLOSED guard on a config pairing that breaks silently and expensively.
 *
 * `retry_after` is how long the queue waits before deciding a reserved job died
 * and giving it to another worker. Set it below a job's real runtime and the AI
 * jobs get delivered a SECOND time while the first is still talking to the
 * provider: the duplicate then fails on `Tries(1)` ("attempted too many times"),
 * the operator loses the turn, and the provider was paid twice for it. Laravel's
 * default 90s is fine for short jobs and far below what an LLM call needs, so a
 * new long-running job (or a reset of this config) has to fail here instead.
 */
test('every queued job finishes well inside the queue retry window', function (): void {
    $retryAfter = config()->integer('queue.connections.database.retry_after');

    $jobs = collect(glob(app_path('Jobs/*.php')))
        ->map(fn (string $file): string => 'App\\Jobs\\'.pathinfo($file, PATHINFO_FILENAME))
        ->filter(fn (string $class): bool => class_exists($class) && ! new ReflectionClass($class)->isAbstract());

    // Sanity check: discovery actually found the long-running AI jobs, so a
    // glob/namespace regression cannot make this guard vacuously pass.
    expect($jobs->all())->toContain(
        ChatEditPageJob::class,
        GenerateSiteDraftJob::class,
    );

    foreach ($jobs as $class) {
        $attributes = new ReflectionClass($class)->getAttributes(Timeout::class);

        if ($attributes === []) {
            continue;
        }

        $timeout = $attributes[0]->newInstance()->timeout;

        expect($timeout)->toBeLessThan($retryAfter, sprintf(
            '%s declares a %ds timeout but the queue re-dispatches a reserved job after %ds, '
            .'so a slow run would be delivered twice and then fail on its try limit. '
            .'Raise queue.connections.database.retry_after above every job timeout.',
            $class,
            $timeout,
            $retryAfter,
        ));
    }
});
