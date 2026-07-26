<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Enums\PageStatus;
use App\Models\Page;

/**
 * Takes a page live, or back to draft. Everything that "publishing" comes to
 * mean (cache busting, sitemap pings, notifications) belongs here, so the two
 * entry points can never drift apart:
 *
 *  - the Pages table's row action publishes the LAST SAVED content;
 *  - the editor's Publish button saves the open draft first, then calls this
 *    ("publish what you see").
 *
 * That difference is deliberate; the side effects of the verb itself are not.
 */
final readonly class PublishPage
{
    public function handle(Page $page, bool $published = true): Page
    {
        $page->update(['status' => $published ? PageStatus::Published : PageStatus::Draft]);

        return $page;
    }
}
