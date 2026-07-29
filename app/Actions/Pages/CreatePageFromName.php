<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Enums\PageStatus;
use App\Models\Page;
use Illuminate\Database\UniqueConstraintViolationException;
use Z3d0X\FilamentFabricator\Facades\FilamentFabricator;

/**
 * Creates a page from nothing but a typed name — the site canvas's
 * right-click gesture. Everything else is inferred: the slug from the name
 * ({@see UniquePageSlug}), the layout from the registry's default, no parent,
 * no blocks, and Draft status so an accidental page never appears on the
 * public site.
 *
 * The retry loop is not defensive padding. `UniquePageSlug` reads before the
 * insert, so two operators naming a page "Contact" at the same moment both
 * see "contact" free and one hits the `unique(tenant_id, slug, parent_id)`
 * index. Re-deriving the slug after the violation resolves it, because by
 * then the winning row is visible.
 *
 * Must run in tenant context (the Page model's RequiresTenantContext guard
 * enforces it; RLS scopes the slug lookup).
 */
final readonly class CreatePageFromName
{
    /**
     * Bounded so a genuinely unsatisfiable insert surfaces as itself rather
     * than spinning. Losing three races in a row is not a slug problem.
     */
    private const int MAX_ATTEMPTS = 3;

    public function __construct(private UniquePageSlug $slugs) {}

    public function handle(string $title): Page
    {
        $title = mb_trim($title);

        for ($attempt = 1; ; $attempt++) {
            try {
                return Page::query()->create([
                    'tenant_id' => tenant('id'),
                    'title' => $title,
                    'slug' => $this->slugs->handle($title),
                    // The same default Fabricator's own create form uses.
                    'layout' => FilamentFabricator::getDefaultLayoutName(),
                    'parent_id' => null,
                    'blocks' => [],
                    'status' => PageStatus::Draft,
                ]);
            } catch (UniqueConstraintViolationException $exception) {
                throw_if($attempt >= self::MAX_ATTEMPTS, $exception);
            }
        }
    }
}
