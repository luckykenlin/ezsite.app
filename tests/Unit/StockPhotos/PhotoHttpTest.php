<?php

declare(strict_types=1);

use App\StockPhotos\PhotoHttp;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

it('retries a server error and returns the last response instead of throwing', function (): void {
    Http::fake(['example.test/*' => Http::sequence()
        ->push('', 500)
        ->push('', 500)
        ->push('ok', 200)]);

    $response = PhotoHttp::client()->get('https://example.test/photo.jpeg');

    expect($response->body())->toBe('ok');

    Http::assertSentCount(config()->integer('stock-photos.http_attempts'));
});

it('retries a dropped connection', function (): void {
    $attempts = 0;

    Http::fake(['example.test/*' => function () use (&$attempts) {
        $attempts++;

        return $attempts < 3
            ? throw new ConnectionException('the wire is down')
            : Http::response('ok');
    }]);

    expect(PhotoHttp::client()->get('https://example.test/photo.jpeg')->body())->toBe('ok')
        ->and($attempts)->toBe(3);
});

/*
 * The predicate is the whole reason this class exists, so its negative half is
 * asserted as its own case: a 429 means the app-side limiter in PexelsProvider
 * already lost, and asking again would only dig further into the provider's
 * ceiling. A 404 is an answer, not a transient — the photo is not there.
 */
it('does not retry a client error', function (int $status): void {
    Http::fake(['example.test/*' => Http::response('', $status)]);

    $response = PhotoHttp::client()->get('https://example.test/photo.jpeg');

    expect($response->status())->toBe($status);

    Http::assertSentCount(1);
})->with([429, 404]);

it('gives up after the configured retries and still answers with the response', function (): void {
    Http::fake(['example.test/*' => Http::response('', 503)]);

    // Never an exception: every caller degrades to "no photo" by INSPECTING the
    // response, so `retry()`'s default throw-on-exhaustion would turn a missing
    // photograph into a failed job.
    $response = PhotoHttp::client()->get('https://example.test/photo.jpeg');

    expect($response->serverError())->toBeTrue();

    Http::assertSentCount(config()->integer('stock-photos.http_attempts'));
});

it('bounds the connect handshake separately from the whole request', function (): void {
    Http::fake();

    $options = PhotoHttp::client()->getOptions();

    expect($options['connect_timeout'])->toBe(config()->integer('stock-photos.http_connect_timeout'))
        ->and($options['timeout'])->toBe(config()->integer('stock-photos.http_timeout'))
        // A connect bound at or above the total budget would never fire.
        ->and($options['connect_timeout'])->toBeLessThan($options['timeout']);
});
