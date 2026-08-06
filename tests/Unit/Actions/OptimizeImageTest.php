<?php

declare(strict_types=1);

use App\Actions\OptimizeImage;
use App\Images\OptimizedImage;
use Illuminate\Support\Facades\Log;

function optimize(string $bytes, ?int $maxWidth = null): OptimizedImage
{
    return resolve(OptimizeImage::class)->handle($bytes, $maxWidth);
}

it('re-encodes a photograph to webp and reports what it actually produced', function (): void {
    // The measured problem this exists for: Pexels' `large2x` renditions are
    // the right SIZE for a hero and arrive near-lossless at 2–3 MB, so the
    // format pass alone is most of the win.
    $source = $this->bandedPng([[200, 40, 90], [40, 90, 200]], size: 600, width: 900);

    $optimized = optimize($source);

    expect($optimized->extension)->toBe('webp')
        ->and($optimized->mimeType)->toBe('image/webp')
        ->and($optimized->width)->toBe(900)
        ->and($optimized->height)->toBe(600)
        ->and($optimized->size())->toBe(mb_strlen($optimized->bytes, '8bit'))
        ->and($optimized->size())->toBeLessThan(mb_strlen($source, '8bit'))
        // Really WebP, not a PNG with a hopeful extension.
        ->and(mb_substr($optimized->bytes, 8, 4))->toBe('WEBP');
});

it('caps a large upload at the configured width and keeps its aspect ratio', function (): void {
    config(['images.max_width' => 400]);

    $optimized = optimize($this->bandedPng([[10, 120, 200]], size: 600, width: 1200));

    expect($optimized->width)->toBe(400)
        ->and($optimized->height)->toBe(200);
});

it('never enlarges an image that is already narrower than the ceiling', function (): void {
    // Scaling a 200px logo up to a 1880px hero would cost bytes to add
    // blurriness — `scaleDown`, not `resize`.
    config(['images.max_width' => 1880]);

    $optimized = optimize($this->bandedPng([[10, 120, 200]], size: 120, width: 200));

    expect($optimized->width)->toBe(200)
        ->and($optimized->height)->toBe(120);
});

it('honours a caller that asks for a tighter ceiling than the config', function (): void {
    config(['images.max_width' => 1880]);

    expect(optimize($this->bandedPng([[10, 120, 200]], size: 500, width: 1000), 250)->width)->toBe(250);
});

it('leaves the bytes alone when re-encoding would make them bigger', function (): void {
    // An image that is ALREADY more compressed than this action's quality
    // setting comes out of the encoder larger than it went in — re-encoding a
    // q15 WebP at q78 adds half again in bytes and no detail. Optimising is
    // not a promise that can be kept for every input, and where it cannot be
    // kept the original has to survive untouched.
    $source = $this->noisyWebp(quality: 15);

    $optimized = optimize($source);

    expect($optimized->bytes)->toBe($source)
        ->and($optimized->extension)->toBe('webp')
        ->and($optimized->mimeType)->toBe('image/webp')
        ->and($optimized->width)->toBe(200);
});

it('passes an undecodable file through rather than losing it, with a log', function (): void {
    // Losing a customer's logo to a decoder edge case would be a far worse
    // outcome than storing it at its original weight.
    Log::spy();

    $optimized = optimize('this is not an image');

    expect($optimized->bytes)->toBe('this is not an image')
        ->and($optimized->extension)->toBe('bin')
        ->and($optimized->mimeType)->toBe('application/octet-stream')
        ->and($optimized->width)->toBe(0)
        ->and($optimized->height)->toBe(0);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => $message === 'image.optimize_failed')
        ->once();
});

it('describes an untouched image by its real type, read from the header', function (): void {
    // The passthrough path still has to produce a usable `ext`/`type` for the
    // media row. A 1x1 GIF is already smaller than any WebP container, so it
    // is never re-encoded — and it must not be recorded as one.
    $optimized = optimize(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));

    expect($optimized->mimeType)->toBe('image/gif')
        ->and($optimized->extension)->toBe('gif')
        ->and($optimized->width)->toBe(1);
});
