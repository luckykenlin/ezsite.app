<?php

declare(strict_types=1);

use App\Actions\Templates\FillTemplatePlaceholders;
use App\Ai\SiteDraftValidator;
use App\Design\StylePreset;
use App\Site\Blocks\BlockShape;
use App\Site\Blocks\BlockVocabulary;
use App\Site\Blocks\LayoutAxis;
use App\Templates\PhotoQuery;
use App\Templates\SiteTemplate;
use App\Templates\TemplateField;

/**
 * Every template, by name — the dataset every structural assertion below runs
 * over, so a ninth template is covered the day it is declared.
 *
 * @return array<string, array{SiteTemplate}>
 */
dataset('templates', fn (): array => array_reduce(
    SiteTemplate::cases(),
    fn (array $carry, SiteTemplate $template): array => $carry + [$template->value => [$template]],
    [],
));

/**
 * The filled template run through the same door the AI path uses.
 *
 * @return array{preset: StylePreset, pages: non-empty-list<array{slug: string, title: string, metaDescription: string|null, blocks: list<array{type: string, data: array<string, mixed>}>}>}
 */
function validateTemplate(SiteTemplate $template): array
{
    config(['stock-photos.enabled' => true]);

    $definition = $template->definition();
    $filled = resolve(FillTemplatePlaceholders::class)->handle($definition);

    return resolve(SiteDraftValidator::class)->handle(
        ['preset' => $definition->preset->value, 'pages' => $filled['pages']],
        $definition->demoProfile->name,
    );
}

it('survives the site draft validator with no page and no block dropped', function (SiteTemplate $template): void {
    $definition = $template->definition();
    $validated = validateTemplate($template);

    expect($validated['preset'])->toBe($definition->preset)
        ->and($validated['pages'])->toHaveCount(count($definition->pages));

    foreach ($definition->pages as $index => $authored) {
        expect($validated['pages'][$index]['slug'])->toBe($authored['slug'])
            ->and($validated['pages'][$index]['blocks'])->toHaveCount(count($authored['blocks']));
    }
})->with('templates');

it('keeps every authored field, layout variant and appearance axis', function (SiteTemplate $template): void {
    // The sharp end of "no block dropped": a block survives with the right
    // TYPE even when the sanitizer threw away half its data, so the counts
    // above cannot see a misspelt field name. This walks every key the
    // template author wrote and demands it landed.
    $definition = $template->definition();
    $validated = validateTemplate($template);
    $axes = array_column(LayoutAxis::cases(), 'value');
    $lost = [];

    foreach ($definition->pages as $pageIndex => $authored) {
        foreach ($authored['blocks'] as $blockIndex => $block) {
            $landed = $validated['pages'][$pageIndex]['blocks'][$blockIndex]['data'];
            $where = $authored['slug'].' block '.$blockIndex.' ('.$block['type'].')';

            foreach (array_keys($block['data']) as $field) {
                // The one renamed key: the unprefixed query the author writes
                // is re-attached under the reserved transit key.
                $expected = $field === 'image_query' ? BlockShape::IMAGE_QUERY_KEY : $field;

                if (! array_key_exists($expected, $landed)) {
                    $lost[] = $where.' dropped the field "'.$field.'"';
                }
            }

            if (isset($block[BlockShape::VARIANT_KEY]) && ($landed[BlockShape::VARIANT_KEY] ?? null) !== $block[BlockShape::VARIANT_KEY]) {
                $lost[] = $where.' dropped the variant "'.$block[BlockShape::VARIANT_KEY].'"';
            }

            foreach ($axes as $axis) {
                if (isset($block[$axis]) && ($landed[BlockShape::APPEARANCE_KEY][$axis] ?? null) !== $block[$axis]) {
                    $lost[] = $where.' dropped '.$axis.' "'.$block[$axis].'"';
                }
            }
        }
    }

    expect($lost)->toBeEmpty();
})->with('templates');

it('opens on a home page that leads with a hero', function (SiteTemplate $template): void {
    $pages = $template->definition()->pages;
    $home = $pages[0];

    expect($home['slug'])->toBe('/')
        ->and(array_column($home['blocks'], 'type'))->toContain('hero')
        // Enough page to be worth publishing — the validator's own bar for a
        // home page, asserted here so a thin template fails by name rather
        // than as an opaque SiteDraftUnusable.
        ->and(count($home['blocks']))->toBeGreaterThanOrEqual(3);

    foreach ($pages as $page) {
        expect($page['slug'])->toBeIn(['/', ...SiteDraftValidator::EXTRA_SLUGS])
            ->and(count($page['blocks']))->toBeGreaterThanOrEqual(3);
    }
})->with('templates');

it('declares three brand hexes and a category the photo searches can use', function (SiteTemplate $template): void {
    $definition = $template->definition();

    expect([$definition->brandPrimary, $definition->brandSecondary, $definition->brandAccent])
        ->each->toMatch('/^#[0-9a-fA-F]{6}$/')
        ->and($definition->category)->not->toBeEmpty()
        // The tokens the business row is stamped with keep the preset marker,
        // so the design panel and StampPresetDefaults still know which
        // preset's per-block opinions apply after an override.
        ->and($definition->tokens()->preset)->toBe($definition->preset);
})->with('templates');

it('pre-warms the library with photo searches, and asks a short answerable set of questions', function (SiteTemplate $template): void {
    $definition = $template->definition();

    expect($definition->photoQueries)->not->toBeEmpty()
        ->and($definition->photoQueries)->toContainOnlyInstancesOf(PhotoQuery::class)
        ->and($definition->extraFields)->toContainOnlyInstancesOf(TemplateField::class)
        // Three to six: fewer is not worth a wizard step, more and people skip.
        ->and(count($definition->extraFields))->toBeGreaterThanOrEqual(3)
        ->and(count($definition->extraFields))->toBeLessThanOrEqual(6);

    foreach ($definition->photoQueries as $query) {
        expect($query->query)->not->toBeEmpty()
            ->and($query->count)->toBeGreaterThan(0);
    }

    $keys = array_column($definition->extraFields, 'key');

    expect($keys)->toBe(array_unique($keys));

    foreach ($definition->extraFields as $field) {
        // The example IS the skip-path content, so an empty one would leave a
        // literal placeholder on a published page.
        expect($field->example)->not->toBeEmpty()
            ->and($field->label)->not->toBeEmpty();
    }
})->with('templates');

it('names header and footer chrome that renders', function (SiteTemplate $template): void {
    $vocabulary = resolve(BlockVocabulary::class);
    $chrome = $template->definition()->chrome;

    expect(array_column($chrome, 'type'))->toBe(['header', 'footer']);

    foreach ($chrome as $block) {
        $contract = $vocabulary->get($block['type']);
        $variant = $block['data'][BlockShape::VARIANT_KEY] ?? null;

        expect($contract)->not->toBeNull()
            ->and($variant)->toBeIn($contract->variants)
            ->and(array_keys($block['data']))->each->toBeIn([BlockShape::VARIANT_KEY, ...$contract->fields]);
    }
})->with('templates');

it('gives every template its own demo subdomain, label and gallery copy', function (): void {
    $subdomains = array_map(fn (SiteTemplate $t): string => $t->demoSubdomain(), SiteTemplate::cases());

    expect($subdomains)->toBe(array_unique($subdomains))
        ->and($subdomains)->each->toStartWith('demo-');

    foreach (SiteTemplate::cases() as $template) {
        expect($template->label())->not->toBeEmpty()
            ->and($template->description())->not->toBeEmpty()
            ->and($template->highlights())->toHaveCount(3);
    }
});

it('uses every style preset at least once across the library', function (): void {
    // The gallery is the design system's shop window: a preset no template
    // reaches for is a preset no visitor ever sees.
    $used = array_map(fn (SiteTemplate $t): string => $t->definition()->preset->value, SiteTemplate::cases());

    expect(array_unique($used))->toHaveSameSize(StylePreset::cases());
});
