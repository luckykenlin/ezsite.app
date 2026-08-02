<?php

declare(strict_types=1);

namespace App\Site;

use App\Models\Page;
use App\Models\Post;
use App\Models\ReviewRequest;

/**
 * The absolute public address of anything a tenant site serves.
 *
 * The models' own `getUrl()` methods are RELATIVE on purpose: a queue worker
 * has no request, so `url()` there resolves against the CENTRAL domain, and
 * anything needing an absolute address has to say which host it means. That
 * convention used to live as three near-identical docblocks and a `url()`
 * call at every consumer — this is the one place that knows both halves.
 * Request context only, like `url()` itself.
 */
final readonly class PublicUrl
{
    public static function to(Page|Post|ReviewRequest $model): string
    {
        return url($model->getUrl());
    }

    /**
     * The `/updates` index — a surface with no model to ask.
     */
    public static function updatesIndex(): string
    {
        return url('/'.Post::PATH_PREFIX);
    }
}
