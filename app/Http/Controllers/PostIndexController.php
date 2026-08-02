<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\BuildPostIndexSeoData;
use App\Site\PostFeed;
use Illuminate\Contracts\View\View;

/**
 * The list of a tenant's updates.
 *
 * Reads through {@see PostFeed}, so this page, the home-page block, the nav entry
 * and the offer popup all cost one query between them.
 *
 * It answers 200 even with nothing published: the address is linked from the site
 * chrome and from share cards, and a 404 on a page the site itself points at
 * reads as a broken website. Empty is a sentence, not an error.
 */
final class PostIndexController extends Controller
{
    public function __invoke(PostFeed $feed, BuildPostIndexSeoData $seoData): View
    {
        return view('site.updates.index', [
            'posts' => $feed->latest(PostFeed::PAGE_SIZE),
            'seoData' => $seoData->handle(),
        ]);
    }
}
