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
 *
 * Checked against EVERY connection that declares a `retry_after`, not just the
 * one `queue.default` currently names. This app runs `database`, so pinning the
 * assertion there passed while `redis` and `beanstalkd` sat at Laravel's stock
 * 90s — below GenerateSiteDraftJob's 360s. Switching driver is a one-line env
 * change and a normal thing to do when scaling; it must not be able to reinstate
 * the exact bug this guard exists to prevent while the guard stays green. The
 * test env pins `QUEUE_CONNECTION=sync`, which declares no `retry_after` at all,
 * so reading the default connection would not work here anyway.
 */
test('every queued job finishes well inside the queue retry window', function (): void {
    $windows = collect(config()->array('queue.connections'))
        ->map(fn (mixed $connection): mixed => is_array($connection) ? $connection['retry_after'] ?? null : null)
        ->filter(fn (mixed $retryAfter): bool => is_int($retryAfter));

    // Sanity check: the connections actually carry the key. A config restructure
    // that renamed or dropped it would otherwise leave nothing to assert and
    // this test would pass over an empty list.
    expect($windows->keys()->all())->toContain('database', 'redis', 'beanstalkd');

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

        foreach ($windows as $connection => $retryAfter) {
            expect($timeout)->toBeLessThan($retryAfter, sprintf(
                '%s declares a %ds timeout but the %s queue re-dispatches a reserved job after %ds, '
                .'so a slow run would be delivered twice and then fail on its try limit. '
                .'Raise queue.connections.%s.retry_after above every job timeout.',
                $class,
                $timeout,
                $connection,
                $retryAfter,
                $connection,
            ));
        }
    }
});
