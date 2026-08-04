<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Enums\Locale;
use App\Http\Controllers\Controller;
use App\Site\RobotsDocument;
use Illuminate\Http\Response;

/**
 * robots.txt for the CENTRAL marketing domain. A sibling of the tenant-side
 * RobotsController rather than a shared one: the two disallow different things
 * and advertise different sitemaps, and folding them together would mean one
 * class asking "am I on a tenant right now?" on every crawl.
 *
 * Deliberately a route, not `public/robots.txt`. A static file is served
 * before routing ever runs, so it would shadow the TENANT controller on every
 * tenant domain — the whole installation would advertise the marketing
 * sitemap.
 */
final class RobotsController extends Controller
{
    public function __invoke(RobotsDocument $document): Response
    {
        return $document->respond(
            // One line per language: the wizard used to live at `/start`, and a
            // `Disallow: /start` that no longer matches `/zh/start` is a rule
            // that reads as protection while granting none.
            array_map(
                static fn (Locale $locale): string => '/'.$locale->value.'/start',
                Locale::cases(),
            ),
            route('central.sitemap'),
        );
    }
}
