<?php

declare(strict_types=1);

namespace App\Console\Commands\Library;

use App\Actions\Library\FindOrImportLibraryPhoto;
use App\Models\LibraryPhoto;
use App\StockPhotos\PhotoOrientation;
use App\StockPhotos\StockPhotoProvider;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Grow the shared photo library on purpose, by theme.
 *
 * The library also fills itself through normal use — every site generation and
 * every chat import adds to it — but that only ever covers subjects someone
 * happened to ask for. This is the handle for seeding it ahead of demand: run it
 * for the trades the product serves, and the next generated site already has
 * photographs to draw on without touching the provider at all.
 *
 * Deliberately imports into the catalogue ONLY, with no adoption: there is no
 * tenant here to adopt into, and pretending otherwise is how a cross-tenant
 * command ends up writing tenant-owned rows. The photos become usable by every
 * site the moment they land.
 */
#[Signature('library:import {query : What to search for, in English — e.g. "coffee shop interior"} {--count=10 : How many photos to import} {--orientation=landscape : landscape, portrait or square}')]
#[Description('Import stock photos into the shared photo library')]
final class ImportLibraryPhotos extends Command
{
    public function handle(StockPhotoProvider $provider, FindOrImportLibraryPhoto $import): int
    {
        $query = (string) $this->argument('query');
        $count = (int) $this->option('count');
        $orientation = PhotoOrientation::tryFrom((string) $this->option('orientation'));

        if ($count < 1) {
            $this->components->error('--count must be at least 1.');

            return self::FAILURE;
        }

        if (! $orientation instanceof PhotoOrientation) {
            $this->components->error(sprintf(
                '--orientation must be one of: %s.',
                implode(', ', array_column(PhotoOrientation::cases(), 'value')),
            ));

            return self::FAILURE;
        }

        $results = $provider->search($query, $orientation, $count);

        if ($results === []) {
            // The NullProvider path too: with no key configured every search is
            // empty, and saying why is more useful than reporting zero imports.
            $this->components->warn(sprintf(
                'The provider returned nothing for "%s". Check STOCK_PHOTOS_ENABLED and the provider key.',
                $query,
            ));

            return self::SUCCESS;
        }

        $imported = 0;
        $reused = 0;
        $failed = 0;

        foreach ($results as $result) {
            $photo = $import->handle($result, $query);

            match (true) {
                ! $photo instanceof LibraryPhoto => $failed++,
                $photo->wasRecentlyCreated => $imported++,
                default => $reused++,
            };
        }

        $this->components->info(sprintf(
            '%d imported, %d already in the library, %d failed.',
            $imported,
            $reused,
            $failed,
        ));

        return self::SUCCESS;
    }
}
