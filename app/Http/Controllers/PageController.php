<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Page as AppPage;
use Z3d0X\FilamentFabricator\Http\Controllers\PageController as FabricatorPageController;
use Z3d0X\FilamentFabricator\Models\Contracts\Page;
use Z3d0X\FilamentFabricator\Services\PageRoutesService;

/**
 * The public tenant-site page controller: identical to Fabricator's, plus a
 * hard 404 for draft pages so AI-generated drafts stay invisible until the
 * operator publishes them.
 */
final class PageController extends FabricatorPageController
{
    public function __invoke(?Page $filamentFabricatorPage = null): string
    {
        if (blank($filamentFabricatorPage)) {
            $filamentFabricatorPage = resolve(PageRoutesService::class)->findPageOrFail('/');
        }

        abort_if($filamentFabricatorPage instanceof AppPage && $filamentFabricatorPage->isDraft(), 404);

        return parent::__invoke($filamentFabricatorPage);
    }
}
