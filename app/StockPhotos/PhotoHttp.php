<?php

declare(strict_types=1);

namespace App\StockPhotos;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The one HTTP policy every stock-photo call runs under: search
 * ({@see PexelsProvider}) and the binary download
 * ({@see \App\Actions\Library\FindOrImportLibraryPhoto}).
 *
 * A class rather than the four options inlined at both call sites, because the
 * retry PREDICATE is the part that must not drift: retrying a 429 would dig the
 * app deeper into the rate limit that {@see PexelsProvider}'s own limiter exists
 * to stay clear of, and retrying a 404 spends the job's budget on a photo that
 * was never there. Only a connection failure or a 5xx is worth a second look.
 *
 * Not an Http macro, which would be the framework-native shape: the facade
 * cannot advertise a macro to PHPStan, and this project holds a 100% type
 * coverage gate.
 *
 * `throw: false` is load-bearing. `retry()` otherwise raises RequestException
 * once the attempts are spent, and both callers are built on inspecting the
 * response instead — every stock-photo failure path degrades to "no photo" with
 * a log line, never an exception, because the worst acceptable outcome here is a
 * draft without photographs.
 */
final readonly class PhotoHttp
{
    public static function client(): PendingRequest
    {
        return Http::timeout(config()->integer('stock-photos.http_timeout'))
            ->connectTimeout(config()->integer('stock-photos.http_connect_timeout'))
            ->retry(
                config()->integer('stock-photos.http_attempts'),
                config()->integer('stock-photos.http_retry_delay_ms'),
                self::worthRetrying(...),
                throw: false,
            );
    }

    /**
     * A transient failure, as opposed to an answer.
     */
    private static function worthRetrying(Throwable $throwable): bool
    {
        return $throwable instanceof ConnectionException
            || ($throwable instanceof RequestException && $throwable->response->serverError());
    }
}
