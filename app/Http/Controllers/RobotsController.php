<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Site\RobotsDocument;
use Illuminate\Http\Response;

/**
 * robots.txt for the tenant site: crawlers get the whole public site, the
 * admin panel and the editor's preview route stay out of the index, and the
 * sitemap is advertised on the tenant's own domain.
 */
final class RobotsController extends Controller
{
    public function __invoke(RobotsDocument $document): Response
    {
        return $document->respond(['/admin', '/_editor'], url('/sitemap.xml'));
    }
}
