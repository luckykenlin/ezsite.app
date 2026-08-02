<?php

declare(strict_types=1);

use App\Actions\Pages\CreatePageFromName;
use App\Actions\Pages\DuplicatePage as DuplicatePageAction;
use App\Actions\Pages\StampPresetDefaults;
use App\Ai\PageDraft;
use App\Ai\Tools\CreatePage;
use App\Ai\Tools\DuplicatePage;
use App\Design\StylePreset;
use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\Tenant;
use App\Site\Blocks\BlockVocabulary;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

/**
 * The two page-level verbs — the only ones that WRITE rather than stage, because
 * a page cannot be staged: there is no in-memory form of "there will be a Services
 * page" for the operator to review.
 *
 * Every test here is really about one of the properties that makes that write
 * safe: the result is always a hidden draft, the live site is untouched, nothing
 * navigates, and neither deleting nor publishing is on offer at all.
 *
 * A feature test rather than a unit one: both verbs go through Eloquent and RLS,
 * and "it is a draft" is a fact about a stored row.
 */
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->withDomain('acme')->create();
});

function createPageTool(?StylePreset $preset = null): CreatePage
{
    return new CreatePage(
        resolve(CreatePageFromName::class),
        resolve(BlockVocabulary::class),
        resolve(StampPresetDefaults::class),
        $preset,
    );
}

it('creates a page as a hidden draft, with a slug derived from the name', function (): void {
    $result = $this->runInTenant($this->tenant, fn (): string => createPageTool()
        ->handle(new Request(['title' => 'Our Services'])));

    $page = $this->runInTenant($this->tenant, fn (): Page => Page::query()->where('slug', 'our-services')->sole());

    expect($page->title)->toBe('Our Services')
        // The property the whole design rests on: nothing about the live site
        // changed, because a draft is absent from the site and from sitemap.xml.
        ->and($page->status)->toBe(PageStatus::Draft)
        ->and($page->blocks)->toBeEmpty()
        ->and($result)->toContain('hidden draft at /our-services')
        // The reply must point at the site canvas, since nothing navigated.
        ->and($result)->toContain('site canvas')
        // And must not promise to fill it in: the block tools address the OPEN
        // page, so the new one is out of reach until the operator switches.
        ->and($result)->toContain('until they switch to it');
});

it('starts a page with a skeleton of real sections, each with its sample copy', function (): void {
    $this->runInTenant($this->tenant, fn (): string => createPageTool()
        ->handle(new Request(['title' => 'Services', 'sections' => ['hero', 'offerings', 'cta']])));

    $blocks = $this->runInTenant($this->tenant, fn (): array => Page::query()->where('slug', 'services')->sole()->blocks);

    expect(array_column($blocks, 'type'))->toBe(['hero', 'offerings', 'cta'])
        // Sample copy, not empty fields: an empty required field would fail the
        // block's own validation the moment the operator opened the page.
        ->and($blocks[0]['data']['heading'])->not->toBeEmpty()
        // The variant is stamped and comes FIRST, matching what AddPageBlock
        // writes — key order is load-bearing for RecordPageRevision's `===`.
        ->and(array_key_first($blocks[0]['data']))->toBe('variant');
});

/*
 * A new page should look like it belongs to the site before anyone types into it,
 * so the site's preset supplies both halves — layouts and section rhythm.
 */
it("lays the new page out in the site's own style when it is on a preset", function (): void {
    $this->runInTenant($this->tenant, fn (): string => createPageTool(StylePreset::BoldEditorial)
        ->handle(new Request(['title' => 'Work', 'sections' => ['hero', 'gallery']])));

    $blocks = $this->runInTenant($this->tenant, fn (): array => Page::query()->where('slug', 'work')->sole()->blocks);

    expect($blocks[0]['data']['variant'])->toBe('full-bleed-overlay')
        ->and($blocks[1]['data']['appearance'])
        ->toBe(StylePreset::BoldEditorial->blockAppearanceDefaults()['gallery']);
});

it("falls back to each block's own default layout when the site has no preset", function (): void {
    // A site on a custom palette has detached from its preset, and the block's
    // own default is then exactly right.
    $this->runInTenant($this->tenant, fn (): string => createPageTool()
        ->handle(new Request(['title' => 'Work', 'sections' => ['hero']])));

    $blocks = $this->runInTenant($this->tenant, fn (): array => Page::query()->where('slug', 'work')->sole()->blocks);

    expect($blocks[0]['data']['variant'])->toBe('centered-minimal')
        ->and($blocks[0]['data'])->not->toHaveKey('appearance');
});

it('refuses to create anything when a requested section is not a real type', function (): void {
    $result = $this->runInTenant($this->tenant, fn (): string => createPageTool()
        ->handle(new Request(['title' => 'Services', 'sections' => ['hero', 'carousel']])));

    // Nothing at all, not a page with the good sections: a half-built page is
    // harder to explain than none.
    expect($this->runInTenant($this->tenant, fn (): int => Page::query()->count()))->toBe(0)
        ->and($result)->toContain('carousel is not a section you can add');
});

it('refuses site chrome as a page section', function (): void {
    $result = $this->runInTenant($this->tenant, fn (): string => createPageTool()
        ->handle(new Request(['title' => 'Services', 'sections' => ['header']])));

    expect($this->runInTenant($this->tenant, fn (): int => Page::query()->count()))->toBe(0)
        ->and($result)->toContain('header is not a section you can add');
});

it('asks for a name rather than inventing one', function (mixed $title): void {
    $result = $this->runInTenant($this->tenant, fn (): string => createPageTool()
        ->handle(new Request(['title' => $title])));

    expect($this->runInTenant($this->tenant, fn (): int => Page::query()->count()))->toBe(0)
        ->and($result)->toContain('needs a name');
})->with([
    'missing' => [null],
    'empty' => [''],
    'whitespace' => ['   '],
    'not a string' => [42],
]);

it('publishes the addable types in its schema, chrome excluded', function (): void {
    $serialized = json_decode(json_encode(array_map(
        fn ($type): array => $type->toArray(),
        createPageTool()->schema(new JsonSchemaTypeFactory),
    )), associative: true);

    // Named types, not `->toBe($vocabulary->pageTypeNames())` — the schema is
    // built from exactly that call, so restating it holds however it is built.
    expect($serialized['sections']['items']['enum'])
        ->toContain('hero', 'features', 'testimonials', 'gallery', 'cta', 'contact', 'heading')
        ->not->toContain('header')
        ->not->toContain('footer')
        // The two verbs it does NOT have, stated where the model reads it.
        ->and(createPageTool()->description())->toContain('cannot delete or publish');
});

/*
 * The copy takes what is ON SCREEN, not what is on disk. "Make another one like
 * this" means the page the operator is looking at — including edits they have not
 * saved, and including anything this same turn wrote moments earlier.
 */
it('copies the working draft rather than the saved page', function (): void {
    $page = $this->runInTenant($this->tenant, function (): Page {
        $page = resolve(CreatePageFromName::class)->handle('Services');
        $page->update(['blocks' => [['type' => 'hero', 'data' => ['heading' => 'Saved headline']]]]);

        return $page;
    });

    // The draft the operator is looking at has moved on from what is stored.
    $draft = new PageDraft([
        ['key' => 'k1', 'type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Unsaved headline']],
    ]);

    $result = $this->runInTenant($this->tenant, fn (): string => new DuplicatePage(
        $draft,
        $page,
        resolve(DuplicatePageAction::class),
    )->handle(new Request([])));

    $copy = $this->runInTenant($this->tenant, fn (): Page => Page::query()->where('slug', 'services-copy')->sole());

    expect($copy->blocks[0]['data']['heading'])->toBe('Unsaved headline')
        // The transient editor key is not part of the persisted shape.
        ->and($copy->blocks[0])->not->toHaveKey('key')
        ->and($copy->status)->toBe(PageStatus::Draft)
        ->and($copy->title)->toBe('Services (copy)')
        ->and($result)->toContain('unsaved changes included')
        ->and($result)->toContain('site canvas');

    // The original is untouched, on disk and in status.
    expect($this->runInTenant($this->tenant, fn (): Page => $page->fresh())->blocks[0]['data']['heading'])
        ->toBe('Saved headline');
});

it('takes no arguments, so the model cannot name the copy for the operator', function (): void {
    $page = $this->runInTenant($this->tenant, fn (): Page => resolve(CreatePageFromName::class)->handle('Services'));

    $tool = new DuplicatePage(new PageDraft([]), $page, resolve(DuplicatePageAction::class));

    expect($tool->schema(new JsonSchemaTypeFactory))->toBeEmpty()
        ->and($tool->description())->toContain('cannot delete or publish');
});
