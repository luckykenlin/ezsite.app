<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\BuildPostSeoData;
use App\Models\Post;
use App\Site\BindResolver;
use App\Site\PostBody;
use Illuminate\Contracts\View\View;

/**
 * One update, at its permanent address on the tenant's own site.
 *
 * That permanence is the whole point of the page: it is what an owner can put in
 * an Instagram bio, what a Facebook share resolves to, and what a Google
 * Business Profile button will eventually point at. So:
 *
 * - A DRAFT 404s, mirroring {@see PageController}'s behaviour for draft pages.
 * - An EXPIRED update still answers 200. Its window closing means it drops out
 *   of the feed, the nav and the popup — but a link already live in somebody's
 *   Facebook feed must not start returning a 404 three weeks later. The view
 *   swaps the call to action for "this offer ended on 14 March" instead.
 *
 * The route binds on `{post:slug}`, which is RLS-scoped, so another tenant's slug
 * 404s with no filtering here at all — the same property SitemapController relies
 * on.
 */
final class PostController extends Controller
{
    public function __invoke(Post $post, BuildPostSeoData $seo, BindResolver $bindResolver): View
    {
        abort_unless($post->isPublished(), 404);

        return view('site.updates.show', [
            'post' => $post,
            'seoData' => $seo->handle($post),
            // The CALL button references the business's phone, never a copy on
            // the update; resolved here so the view stays free of container
            // lookups.
            'business' => $bindResolver->business(),
            'paragraphs' => new PostBody($post->body)->paragraphs(),
        ]);
    }
}
