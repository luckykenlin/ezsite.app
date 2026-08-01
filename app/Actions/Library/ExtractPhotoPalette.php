<?php

declare(strict_types=1);

namespace App\Actions\Library;

use App\StockPhotos\PhotoPalette;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Read a photograph's dominant colours and overall brightness out of its raw
 * bytes, so the shared library can be searched by colour and — the part that
 * actually changes what the site looks like — so a caller can ask for a photo
 * dark enough to carry overlaid text.
 *
 * Deliberately hand-rolled sampling rather than the driver's own
 * `reduceColors()`: this way the output is deterministic and unit-testable
 * against a synthesised image, and it does not depend on a quantiser whose
 * behaviour differs between the GD and Imagick drivers. GD specifically,
 * because it is the driver present everywhere.
 *
 * Never throws. A photo whose palette could not be read is still a perfectly
 * good photo — the import keeps it with null colour metadata rather than
 * failing, exactly as a failed download degrades to a photo-less draft.
 */
final readonly class ExtractPhotoPalette
{
    /**
     * The longest edge the image is scaled down to before sampling. 48px is
     * ~2300 samples: enough that a minor colour still registers, cheap enough
     * to run inline during an import.
     */
    private const int SAMPLE_EDGE = 48;

    /**
     * Channel values are bucketed into steps this wide before counting, so
     * that the thousands of near-identical shades in a photograph collapse
     * into a handful of recognisable colours.
     */
    private const int BUCKET_STEP = 32;

    private const int SWATCH_COUNT = 5;

    /**
     * Mean relative luminance below which a photo counts as dark.
     *
     * 0.179 is not arbitrary and is NOT the midpoint: it is the WCAG crossover
     * `sqrt(1.05 × 0.05) − 0.05`, the luminance at which a background contrasts
     * equally with white and with black text. Below it white wins, above it
     * black does — which is exactly the question `is_dark` is asked. Using 0.5
     * (the midpoint of the scale) would call a mid-orange dark, because
     * linearised luminance is steeply non-linear.
     */
    private const float DARK_THRESHOLD = 0.179;

    public function handle(string $bytes): ?PhotoPalette
    {
        try {
            $image = ImageManager::gd()->read($bytes)->scaleDown(self::SAMPLE_EDGE, self::SAMPLE_EDGE);
        } catch (Throwable) {
            return null;
        }

        /** @var array<string, array{count: int, r: int, g: int, b: int}> $buckets */
        $buckets = [];
        $luminance = 0.0;
        $samples = 0;

        for ($y = 0; $y < $image->height(); $y++) {
            for ($x = 0; $x < $image->width(); $x++) {
                $color = $image->pickColor($x, $y);

                if ($color->isTransparent()) {
                    continue;
                }

                $channels = $color->toArray();
                $red = (int) ($channels[0] ?? 0);
                $green = (int) ($channels[1] ?? 0);
                $blue = (int) ($channels[2] ?? 0);

                $key = sprintf(
                    '%d-%d-%d',
                    intdiv($red, self::BUCKET_STEP),
                    intdiv($green, self::BUCKET_STEP),
                    intdiv($blue, self::BUCKET_STEP),
                );

                $bucket = $buckets[$key] ?? ['count' => 0, 'r' => 0, 'g' => 0, 'b' => 0];

                $buckets[$key] = [
                    'count' => $bucket['count'] + 1,
                    'r' => $bucket['r'] + $red,
                    'g' => $bucket['g'] + $green,
                    'b' => $bucket['b'] + $blue,
                ];

                $luminance += $this->relativeLuminance($red, $green, $blue);
                $samples++;
            }
        }

        if ($samples === 0) {
            return null;
        }

        $ranked = [];

        foreach ($buckets as $key => $bucket) {
            $ranked[] = ['key' => $key, ...$bucket];
        }

        // Ties broken by bucket key so two runs over the same image agree.
        usort($ranked, fn (array $a, array $b): int => $b['count'] <=> $a['count'] ?: strcmp($a['key'], $b['key']));

        $swatches = [];

        foreach (array_slice($ranked, 0, self::SWATCH_COUNT) as $bucket) {
            // The bucket's MEAN colour, not its midpoint: a swatch should be a
            // colour that is actually in the photograph.
            $swatches[] = sprintf(
                '#%02x%02x%02x',
                intdiv($bucket['r'], $bucket['count']),
                intdiv($bucket['g'], $bucket['count']),
                intdiv($bucket['b'], $bucket['count']),
            );
        }

        return new PhotoPalette(
            $swatches,
            $swatches[0],
            $luminance / $samples < self::DARK_THRESHOLD,
        );
    }

    /**
     * WCAG relative luminance, on gamma-decoded channels. The linearisation
     * matters here: on raw sRGB values a mid-grey photo reads as much brighter
     * than the eye sees it, and photos would be classed light-enough-for-dark-
     * text when they are not.
     */
    private function relativeLuminance(int $red, int $green, int $blue): float
    {
        return 0.2126 * $this->linearize($red)
            + 0.7152 * $this->linearize($green)
            + 0.0722 * $this->linearize($blue);
    }

    private function linearize(int $channel): float
    {
        $value = $channel / 255;

        return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
    }
}
