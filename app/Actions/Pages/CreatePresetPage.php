<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Jobs\PopulateDraftImagesJob;
use App\Models\Page;
use App\Site\Blocks\BlockShape;
use App\Templates\PagePreset;

/**
 * The "Add a page" picker's write path: a chosen preset becomes a DRAFT page
 * in one INSERT, personalized and styled for this tenant, with the
 * stock-photo follow-up queued when the preset asked for imagery.
 *
 * A thin composition over {@see BuildPresetPageBlocks} and
 * {@see CreatePageFromName} on purpose — the picker must not grow a second
 * page-creation code path. Blank short-circuits the whole pipeline: "no
 * starting blocks" needs no personalization, no sanitizer and no photo job,
 * and reproduces exactly what the canvas's right-click gesture always did.
 *
 * The photo dispatch mirrors {@see \App\Actions\Templates\ProvisionSiteFromTemplate}:
 * fire-and-forget, only when the feature is on AND a block actually carries a
 * query — a preset with no imagery must not cost a queue round trip.
 */
final readonly class CreatePresetPage
{
    public function __construct(
        private BuildPresetPageBlocks $build,
        private CreatePageFromName $create,
    ) {
        //
    }

    /**
     * @param  string|null  $title  the operator's name for the page; blank falls
     *                              back to the preset's own title
     */
    public function handle(PagePreset $preset, ?string $title = null): Page
    {
        $title = is_string($title) && mb_trim($title) !== '' ? mb_trim($title) : null;

        if ($preset === PagePreset::Blank) {
            return $this->create->handle($title ?? $preset->definition()->title);
        }

        $built = $this->build->handle($preset);

        $page = $this->create->handle($title ?? $built['title'], $built['blocks'], $built['metaDescription']);

        if ($this->wantsPhotos($built['blocks'])) {
            dispatch(new PopulateDraftImagesJob((string) tenant('id')));
        }

        return $page;
    }

    /**
     * @param  list<array{type: string, data: array<string, mixed>}>  $blocks
     */
    private function wantsPhotos(array $blocks): bool
    {
        if (! config()->boolean('stock-photos.enabled')) {
            return false;
        }

        foreach ($blocks as $block) {
            if (array_key_exists(BlockShape::IMAGE_QUERY_KEY, $block['data'])) {
                return true;
            }
        }

        return false;
    }
}
