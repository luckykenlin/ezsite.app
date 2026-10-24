<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Jobs\PopulateDraftImagesJob;
use App\Models\Page;
use App\Models\Tenant;
use App\Site\Blocks\BlockShape;
use App\Themes\ThemePage;

/**
 * The "Add a page" picker's write path: a chosen theme page becomes a DRAFT
 * page in one INSERT, personalized and styled for this tenant, with the
 * stock-photo follow-up queued when the page asked for imagery.
 *
 * A thin composition over {@see BuildThemePageBlocks} and
 * {@see CreatePageFromName} on purpose — the picker must not grow a second
 * page-creation code path. Blank (a null page) short-circuits the whole
 * pipeline: "no starting blocks" needs no personalization, no sanitizer and no
 * photo job, and reproduces exactly what the canvas's right-click gesture
 * always did. Null rather than a "blank" ThemePage because blank is the same
 * in every theme and has nothing for a theme to design.
 *
 * The photo dispatch mirrors {@see \App\Actions\Templates\ProvisionSiteFromTemplate}:
 * fire-and-forget, only when the feature is on AND a block actually carries a
 * query — a page with no imagery must not cost a queue round trip.
 */
final readonly class CreateThemePage
{
    public function __construct(
        private BuildThemePageBlocks $build,
        private CreatePageFromName $create,
    ) {
        //
    }

    /**
     * @param  ThemePage|null  $page  null for a blank page
     * @param  string|null  $title  the operator's name for the page; blank falls
     *                              back to the theme page's own title
     */
    public function handle(?ThemePage $page, ?string $title = null): Page
    {
        $title = is_string($title) && mb_trim($title) !== '' ? mb_trim($title) : null;

        if (! $page instanceof ThemePage) {
            return $this->create->handle($title ?? __('Untitled page'));
        }

        $built = $this->build->handle($page);

        $created = $this->create->handle($title ?? $built['title'], $built['blocks'], $built['metaDescription']);

        if ($this->wantsPhotos($built['blocks'])) {
            $tenant = tenant();

            // Narrowing for the type system only — tenant() is typed nullable.
            // The Page insert above already went through RequiresTenantContext,
            // which throws outside tenancy, so this branch cannot miss; a loud
            // else here would be an unreachable line the coverage gate rejects.
            if ($tenant instanceof Tenant) {
                dispatch(new PopulateDraftImagesJob($tenant->id));
            }
        }

        return $created;
    }

    /**
     * @param  list<array{type: string, data: array<string, mixed>}>  $blocks
     */
    private function wantsPhotos(array $blocks): bool
    {
        if (! config()->boolean('stock-photos.enabled')) {
            return false;
        }

        return array_any($blocks, fn (array $block): bool => array_key_exists(BlockShape::IMAGE_QUERY_KEY, $block['data']));
    }
}
